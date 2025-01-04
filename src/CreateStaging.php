<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Arr;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use LamaLama\Clli\Console\Services\CliConfig;
use Laravel\Forge\Exceptions\ValidationException;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class CreateStaging extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    protected InputInterface $input;

    protected OutputInterface $output;

    protected CliConfig $cfg;

    protected Forge $forge;

    protected ?Site $site = null;

    protected ?string $subdomain = null;

    private ?string $serverId = null;

    private ?string $db_password = null;

    private ?string $db_name = null;

    private ?string $db_user = null;

    private ?string $siteIsolatedName = null;

    private ?string $ip = null;

    private ?string $wpUser = null;

    private ?string $repo = null;

    private ?string $wpUserEmail = null;

    private ?string $wpPassword = null;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('lamapress:staging')
            ->addArgument('subdomain', InputArgument::OPTIONAL)
            ->setDescription('Create a staging environment and install WordPress for this project on a Forge server.');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);
        $this->input = $input;
        $this->output = $output;

        $output->write('<fg=white>
 ░▒▓██████▓▒░░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░
 ░▒▓██████▓▒░░▒▓████████▓▒░▒▓████████▓▒░▒▓█▓▒░'.PHP_EOL.PHP_EOL);

    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cfg = new CliConfig;
        $this->forge = new Forge($this->getForgeToken());
        $this->forge->setTimeout(300);
        $this->serverId = $this->getServerId();
        $this->subdomain = $this->getSubdomain();
        $this->repo = $this->calulateRepo();

        // Setup the site
        $sites = $this->forge->sites($this->serverId);

        /* TODO:
        - Get all setup van rules from LamaPressNewCommand to install wordpress
        - Rewrite wp-config with DB credentials
        - Create a summary of what is going to happen
        - Do pre-checks
        - Check which branch is checked out and will be deployed
        */

        spin(fn () => $this->createSite(), 'Creating site');
        info('Site created');
        spin(fn () => $this->createDatabase(), 'Creating database');
        info('Database created');
        spin(fn () => $this->updateCloudflareDns(), 'Updating Cloudflare DNS');
        info('Cloudflare DNS updated');
        spin(fn () => $this->installSsl(), 'Installing SSL certificate');
        info('SSL certificate installed');
        spin(fn () => $this->installSsh(), 'Installing your SSH key');
        info('SSH key installed');
        spin(fn () => $this->installEmptyRepo(), 'Installing empty repository');
        info('Empty repository installed');
        spin(fn () => $this->installWordpress(), 'Installing wordpress');
        info('WordPress installed');
        spin(fn () => $this->installPlugins(), 'Installing plugins');
        info('Plugins installed');
        spin(fn () => $this->installTheme(), 'Installing theme');
        info('Theme installed');
        spin(fn () => $this->getMigrateDbConnectionKey(), 'Retrieving Migrate DB connection key');
        info('Migrate DB connection key retrieved');
        spin(fn () => $this->migrateLocalDatabase(), 'Migrating local database to staging');
        info('Local database migrated');
        spin(fn () => $this->enableQuickDeploy(), 'Enabling quick deploy');
        info('Quick deploy enabled');
        spin(fn () => $this->setDeployscriptAndDeploy(), 'Deploying project');
        info('Project deployed');

        // TODO: Een table+ connection string uitspugen voor makelijk connecten van local naar remote db

        $output = [
            ['site domain: ', $this->fullDomain()],
            ['server username: ', $this->siteIsolatedName()],
            ['Site id: ', $this->siteId()],
            ['DB name', $this->dbName()],
            ['DB username', $this->dbUsername()],
            ['DB password', $this->dbPassword()],
        ];

        table(['Key', 'Value'], $output);

        return 1;
    }

    /**
     * Calculate the repository name
     */
    private function calulateRepo()
    {
        if ($this->repo) {
            return $this->repo;
        }

        $repoFromRemote = shell_exec('git config --get remote.origin.url');
        if ($repoFromRemote) {
            $repoFromRemote = str_replace('.git', '', explode(':', $repoFromRemote)[1] ?? '');
            if ($repoFromRemote) {
                return $this->repo = trim($repoFromRemote);
            }

        }

        return $this->repo = 'lamalamaNL/'.trim(basename(getcwd()));
    }

    /**
     * Create a new site on Forge
     */
    private function createSite()
    {
        $config = [
            'domain' => $this->fullDomain(),
            'project_type' => 'php',
            'aliases' => [],
            'directory' => '/public',
            'isolated' => true,
            'username' => $this->siteIsolatedName(),
            //            "database" => "site_com_db",
            'php_version' => 'php83',
        ];

        try {
            $this->site = $this->forge->createSite($this->serverId, $config);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn ($er, $field) => "$field: ".Arr::first($er))->implode(' :: '));
            exit();
        }

        return $this->site;
    }

    /**
     * Create a new database on Forge
     */
    private function createDatabase()
    {
        try {
            $this->forge->createDatabase($this->serverId, [
                'name' => $this->dbName(),
                'user' => $this->dbUsername(),
                'password' => $this->dbPassword(),
            ]);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn ($er, $field) => "$field: ".Arr::first($er))->implode(' :: '));
            exit();
        }
    }

    /**
     * Update the Cloudflare DNS record
     */
    public function updateCloudflareDns()
    {
        // Get Cloudflare credentials from config
        $cfToken = $this->cfg->get('cloudflare_token');
        if (! $cfToken) {
            $cfToken = text(
                label: 'We need a Cloudflare API token for DNS updates. Please provide your token:',
                required: true
            );
            $this->cfg->set('cloudflare_token', $cfToken);
        }

        $cfZoneId = $this->cfg->get('cloudflare_zone_id');
        if (! $cfZoneId) {
            $cfZoneId = text(
                label: 'We need your Cloudflare Zone ID for lamalama.dev:',
                required: true
            );
            $this->cfg->set('cloudflare_zone_id', $cfZoneId);
        }

        try {
            $key = new \Cloudflare\API\Auth\APIToken($cfToken);
            $adapter = new \Cloudflare\API\Adapter\Guzzle($key);
            $dns = new \Cloudflare\API\Endpoints\DNS($adapter);

            // Create A record
            $result = $dns->addRecord(
                $cfZoneId,
                'A',
                $this->subdomain,
                $this->serverIp(),
                0, // Auto TTL
                false // Proxied through Cloudflare
            );

            if (! $result) {
                throw new \RuntimeException('Failed to create DNS record');
            }

            $this->output->writeln('DNS record created successfully');

            return true;

        } catch (\Exception $e) {
            throw new \RuntimeException('Error updating DNS: '.$e->getMessage());
        }
    }

    /**
     * Validate the DNS record
     */
    private function validateDnsRecord(): bool
    {
        // Allow some time for DNS propagation
        sleep(5);

        $dnsRecord = dns_get_record($this->fullDomain(), DNS_A);

        if (empty($dnsRecord)) {
            $this->output->writeln('DNS record not found');

            return false;
        }

        foreach ($dnsRecord as $record) {
            if ($record['type'] === 'A' && $record['ip'] === $this->serverIp()) {
                return true;
            }
        }

        $this->output->writeln('DNS record found but IP does not match server IP');

        return false;
    }

    /**
     * Install SSL certificate
     */
    private function installSsl()
    {
        try {
            return $this->forge->obtainLetsEncryptCertificate($this->serverId, $this->siteId(), ['domains' => [$this->fullDomain()]], true);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn ($er, $field) => "$field: ".Arr::first($er))->implode(' :: '));
            exit();
        }
    }

    /**
     * Install WordPress
     */
    private function installWordpress()
    {
        $repoProjectName = explode('/', $this->repo)[1];

        $commands = [
            // Install WordPress
            'cd '.$this->fullDomain(),
            'mkdir public',
            'cd public',
            'wp core download',
            'wp config create --dbname="'.$this->dbName().'" --dbuser="'.$this->dbUsername().'" --dbpass="'.$this->dbPassword().'" --dbhost="127.0.0.1" --dbprefix=wp_',
            'wp core install --url="https://'.$this->fullDomain().'" --title="'.ucfirst($repoProjectName).'" --admin_user="'.$this->wpUser().'" --admin_password="'.$this->wpPassword().'" --admin_email="'.$this->wpUserEmail().'"',
        ];

        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Install WordPress plugins
     */
    private function installPlugins()
    {
        $commands = [
            'cd '.$this->fullDomain().'/public',
            // Delete plugins
            'wp plugin delete akismet',
            'wp plugin delete hello',

            // Install plugins and activate
            'wp plugin install https://downloads.lamapress.nl/wp-migrate-db-pro.zip --activate',
            'wp_migrate_license_key='.$this->getMigrateDbLicenseKey(),
            'wp migratedb setting update license $wp_migrate_license_key --user='.$this->wpUserEmail(),
            'wp migratedb setting update pull on',
            'wp migratedb setting update push on',
            'wp migratedb setting get connection-key',
            'wp plugin update --all',
        ];
        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Install WordPress theme
     */
    private function installTheme()
    {
        $repoProjectName = explode('/', $this->repo)[1];
        $commands = [
            // Delete plugins
            'cd '.$this->fullDomain().'/public',

            // Clone Lamapress WP boilerplate
            'cd wp-content/themes',
            'git clone --depth=1 git@github.com:lamalamaNL/'.$repoProjectName.'.git '.$repoProjectName,
            'wp theme activate '.$repoProjectName,

            // Delete default themes
            'wp theme delete twentytwentythree',
            'wp theme delete twentytwentyfour',
            'wp theme delete twentytwentyfive',
        ];

        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Run a command via the Forge API
     */
    private function runCommandViaApi(array $command)
    {
        $siteCommand = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $command);

        info('===============');
        info(print_r($siteCommand, true));

        // foreach ($siteCommand->output as $line) {
        //     info('Result: '.$line);
        // }
    }

    /**
     * Run a command via the deployment script
     */
    private function runCommandViaDeployScript(string $command)
    {
        $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
        $result = $this->forge->deploySite($this->serverId, $this->siteId());

        return $result;
    }

    /**
     * Get the server ID
     */
    private function getServerId()
    {
        $serverId = $this->cfg->get('forge_server_id');
        if ($serverId) {
            return $serverId;
        }
        $serverId = text(label: 'We need a forge server ID for this command. Please provide a forge server ID', required: true);
        $this->cfg->set('forge_server_id', $serverId);

        return $serverId;
    }

    /**
     * Get the Forge API token
     */
    private function getForgeToken()
    {
        $forgeToken = $this->cfg->get('forge_token');
        if ($forgeToken) {
            return $forgeToken;
        }
        $forgeToken = text(label: 'We need a forge token for this command. Please provide a forge token', required: true);
        $this->cfg->set('forge_token', $forgeToken);

        return $forgeToken;
    }

    /**
     * Get the Migrate DB license key
     */
    private function getMigrateDbLicenseKey()
    {
        $migrateDbLicenseKey = $this->cfg->get('wp_migrate_license_key');
        if ($migrateDbLicenseKey) {
            return $migrateDbLicenseKey;
        }
        $migrateDbLicenseKey = text(label: 'We need a Migrate DB license key for this command. Please provide a Migrate DB license key', required: true);
        $this->cfg->set('wp_migrate_license_key', $migrateDbLicenseKey);

        return $migrateDbLicenseKey;
    }

    /**
     * Get the subdomain
     */
    private function getSubdomain()
    {
        $subdomain = $this->input->getArgument('subdomain');
        if ($subdomain) {
            return $subdomain;
        }

        return text(label: 'What is the subdomain we need to deploy to', required: true);

    }

    /**
     * Get the full domain name
     */
    private function fullDomain()
    {
        return $this->subdomain.'.lamalama.dev';
    }

    /**
     * Get the database name
     */
    private function dbName()
    {
        if ($this->db_name) {
            return $this->db_name;
        }

        return $this->db_name = Str::slug('db_'.$this->fullDomain(), '_');
    }

    /**
     * Get the database username
     */
    private function dbUsername()
    {
        if ($this->db_user) {
            return $this->db_user;
        }

        return $this->db_user = Str::slug('db_user_'.$this->fullDomain(), '_');
    }

    /**
     * Get the database password
     */
    private function dbPassword()
    {
        if ($this->db_password) {
            return $this->db_password;
        }

        return $this->db_password = Str::random(14);
    }

    /**
     * Get the site's isolated username
     */
    private function siteIsolatedName()
    {
        if ($this->siteIsolatedName) {
            return $this->siteIsolatedName;
        }

        return $this->siteIsolatedName = Str::slug('siteuser_'.$this->fullDomain(), '_');
    }

    /**
     * Get the web directory path
     */
    private function webdir()
    {
        if (! $this->site) {
            $this->site = $this->forge->site($this->serverId, '2530605');
        }

        return rtrim($this->site->directory, '/');
    }

    /**
     * Get the site ID
     */
    private function siteId()
    {
        if (! $this->site) {
            $this->site = $this->forge->site($this->serverId, '2530605');
        }

        return $this->site->id;
        // die('No site available');
        // TEMP FOR TESTING: Needs to asked
    }

    /**
     * Get the server IP address
     */
    private function serverIp()
    {
        if ($this->ip) {
            return $this->ip;
        }

        return $this->ip = $this->forge->server($this->serverId)->ipAddress;
    }

    /**
     * Get the public SSH key
     */
    private function getPublicKey(): ?string
    {
        $publicKeyFilename = $this->cfg->get('public_key_filename');
        if (! $publicKeyFilename) {
            $publicKeyFilename = text(label: 'We need a public SSH key filename for this command. Please provide a public key filename', required: true);
            $this->cfg->set('public_key_filename', $publicKeyFilename);
        }

        $ssh_key_path = getenv('HOME').'/.ssh/'.$publicKeyFilename;

        // Check if the file exists
        if (file_exists($ssh_key_path)) {
            // Read the contents of the file
            $ssh_key = file_get_contents($ssh_key_path.'.pub');

            if ($ssh_key !== false) {
                // Output the SSH key
                return $ssh_key;
            } else {
                // Error reading the file
                return null;
            }
        } else {
            // File does not exist
            if (confirm('SSH key not found. Would you like to create a new OpenSSH key pair?')) {
                $command = "ssh-keygen -t rsa -b 4096 -C 'clli@lamalama.nl' -f ".$ssh_key_path." -N ''";
                exec($command, $output, $return_value);

                if ($return_value === 0) {
                    return file_get_contents($ssh_key_path.'.pub');
                }

                echo "Error: Failed to create SSH key pair\n";
            } else {
                echo 'Error: SSH key file not found at '.$ssh_key_path."\n";
            }

            return null;
        }

    }

    /**
     * Get the WordPress admin username
     */
    private function wpUser()
    {
        if ($this->wpUser) {
            return $this->wpUser;
        }

        return $this->wpUser = 'Edwin';
    }

    /**
     * Get the WordPress admin password
     */
    private function wpPassword()
    {
        if ($this->wpPassword) {
            return $this->wpPassword;
        }

        return $this->wpPassword = Str::random(9);
    }

    /**
     * Get the WordPress admin email
     */
    private function wpUserEmail()
    {
        if ($this->wpUserEmail) {
            return $this->wpUserEmail;
        }

        return $this->wpUserEmail = 'edwin@lamalama.nl';
    }

    /**
     * Get the private SSH key
     */
    private function getPrivateKey(): ?string
    {
        $ssh_key_path = getenv('HOME').'/.ssh/id_rsa';

        // Check if the file exists
        if (file_exists($ssh_key_path)) {
            // Read the contents of the file
            $ssh_key = file_get_contents($ssh_key_path);

            if ($ssh_key !== false) {
                // Output the SSH key
                return $ssh_key;
            } else {
                // Error reading the file
                return null;
            }
        } else {
            // File does not exist
            echo 'Error: SSH key file not found at '.$ssh_key_path;

            return null;
        }

    }

    /**
     * Install SSH key on the server
     */
    private function installSsh()
    {
        $key = $this->getPublicKey();
        if (! $key) {
            exit('Could not get your public SSH key.');
        }

        $payload = [
            'name' => 'clli_added_key_'.Str::random('8'),
            'key' => trim($key),
            'username' => $this->siteIsolatedName(),
        ];

        try {
            $this->forge->createSSHKey($this->serverId, $payload);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn ($er, $field) => "$field: ".Arr::first($er))->implode(' :: '));
            exit();
        }
    }

    /**
     * Install empty repository
     */
    private function installEmptyRepo()
    {
        $payload = [
            'provider' => 'github',
            'repository' => 'lamalamaNL/empty',
            'branch' => 'main',
            'composer' => false,
        ];

        try {
            $this->forge->installGitRepositoryOnSite($this->serverId, $this->siteId(), $payload);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn ($er, $field) => "$field: ".Arr::first($er))->implode(' :: '));
            exit();
        }

    }

    /**
     * Set deployment script and deploy
     */
    private function setDeployscriptAndDeploy()
    {
        $this->runCommandViaApi(['command' => 'pwd']);

        $commands = [
            'cd $FORGE_SITE_PATH/public/wp-content/themes/pum',
            'npm install',
            'npm run build',
        ];

        echo collect($commands)->implode(' && ');
        $output = $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
        var_dump($output);
    }

    /**
     * Get the Migrate DB connection key
     */
    private function getMigrateDbConnectionKey()
    {
        $command = ['command' => 'cd '.$this->fullDomain().'/public && wp migratedb setting get connection-key'];

        try {
            info($this->serverId);
            info($this->siteId());
            info(print_r($command, true));

            $result = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $command);

            $siteCommand = $result;

            // Wait for command to complete
            while ($siteCommand->status === 'running' || $siteCommand->status === 'waiting') {
                sleep(1);
                $result = $this->forge->getSiteCommand($this->serverId, $this->siteId(), $siteCommand->id);
                $siteCommand = $result[0];

                info(print_r($result, true));
            }

            if ($siteCommand->status === 'finished') {
                // Extract connection key from output
                $output = trim($siteCommand->output);
                if (! empty($output)) {
                    return $output;
                }
            }

            throw new \RuntimeException('Failed to get connection key: '.($siteCommand->output ?? 'No output'));
        } catch (\Exception $e) {
            throw new \RuntimeException('Error getting connection key: '.$e->getMessage());
        }
    }

    /**
     * Migrate the local database to staging
     */
    private function migrateLocalDatabase()
    {
        $localUrl = exec('wp option get siteurl');
        $remoteUrl = 'https://'.$this->fullDomain();
        $migrateKey = $this->getMigrateDbConnectionKey();

        $commands = [
            "wp migratedb push $remoteUrl ".
            escapeshellarg($migrateKey).
            ' --find='.escapeshellarg($localUrl).
            ' --replace='.escapeshellarg($remoteUrl).
            ' --media=all '.
            ' --plugin-files=all',
        ];

        return $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Enable quick deploy
     */
    private function enableQuickDeploy()
    {
        return $this->forge->enableQuickDeploy($this->serverId, $this->siteId());
    }
}
