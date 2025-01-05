<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Arr;
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
            ->setDescription('Create a new staging environment for a WordPress site on Laravel Forge');
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

        $startTime = microtime(true);
        $initialStartTime = microtime(true);

        $steps = [
            ['checkClliConfig', 'Checking CLLI config'],
            ['initializeCommand', 'Initializing command'],
            ['checkThemeFolder', 'Theme folder check'],
            ['createSite', 'Creating site'],
            ['createDatabase', 'Creating database'],
            ['updateCloudflareDns', 'Updating Cloudflare DNS'],
            ['installSsl', 'Installing SSL certificate'],
            ['installSsh', 'Installing your SSH key'],
            ['installEmptyRepo', 'Installing empty repository'],
            ['installWordPress', 'Installing WordPress'],
            ['installPlugins', 'Installing plugins'],
            ['installTheme', 'Installing theme'],
            ['getMigrateDbConnectionKey', 'Retrieving Migrate DB connection key'],
            ['migrateLocalDatabase', 'Migrating local to staging'],
            ['setBuildScriptAndDeploy', 'Building project'],
            ['enableQuickDeploy', 'Enabling quick deploy'],
            ['setFinalDeploymentScript', 'Setting final deployment script'],
        ];

        foreach ($steps as [$method, $message]) {
            $startTime = microtime(true);
            spin(fn () => $this->$method(), $message);
            info("✅ $message completed (".round(microtime(true) - $startTime, 2).'s)');
        }

        info('🎉 All done in '.round(microtime(true) - $initialStartTime, 2).'s');

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

        return Command::SUCCESS;
    }

    /**
     * Initialize the command dependencies
     */
    private function initializeCommand(): void
    {
        $this->forge = new Forge($this->getForgeToken());
        $this->forge->setTimeout(300);
        $this->serverId = $this->getServerId();
        $this->subdomain = $this->getSubdomain();
        $this->repo = $this->calulateRepo();
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
     * Check if the CLLI config is complete
     */
    private function checkClliConfig()
    {
        $requiredKeys = [
            'forge_token' => 'Get this from forge.laravel.com/user/profile#/api',
            'forge_server_id' => 'Found in the URL when viewing a server on forge.laravel.com',
            'cloudflare_token' => 'Generate at dash.cloudflare.com/profile/api-tokens',
            'cloudflare_zone_id' => 'Found in the Overview tab of your domain on dash.cloudflare.com',
            'wp_migrate_license_key' => 'Available in your WP Migrate account at deliciousbrains.com/my-account',
        ];

        foreach ($requiredKeys as $key => $help) {
            if (! $this->cfg->get($key)) {
                info($help);
                $value = text(label: "CLLI config is missing the $key key. Please provide a value", required: true);
                $this->cfg->set($key, $value);
            }
        }

        return true;
    }

    /**
     * Check if the theme folder exists
     */
    private function checkThemeFolder()
    {
        if (! str_contains(getcwd(), 'wp-content/themes/')) {
            throw new \RuntimeException('Theme folder not found, run this command from the theme folder');
        }

        return true;
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
        } catch (\Exception $e) {
            throw new \RuntimeException('Error updating DNS: '.$e->getMessage());
        }

        return true;
    }

    /**
     * Install SSL certificate
     */
    private function installSsl()
    {
        try {
            return $this->forge->obtainLetsEncryptCertificate($this->serverId, $this->siteId(), ['domains' => [$this->fullDomain()]], true);
        } catch (ValidationException $e) {
            throw new \RuntimeException('Error installing SSL: '.$e->getMessage());
        }
    }

    /**
     * Install WordPress
     */
    private function installWordPress()
    {
        $themeFolderName = explode('/', $this->repo)[1];

        $commands = [
            // Go to site root
            'cd $FORGE_SITE_PATH',

            // Create public folder
            'mkdir public',

            // Go to public folder
            'cd public',

            // Download WordPress
            'wp core download',

            // Create config
            'wp config create --dbname="'.$this->dbName().'" --dbuser="'.$this->dbUsername().'" --dbpass="'.$this->dbPassword().'" --dbhost="127.0.0.1" --dbprefix=wp_',

            // Install WordPress
            'wp core install --url="https://'.$this->fullDomain().'" --title="'.ucfirst($themeFolderName).'" --admin_user="'.$this->wpUser().'" --admin_password="'.$this->wpPassword().'" --admin_email="'.$this->wpUserEmail().'"',
        ];

        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Install WordPress plugins
     */
    private function installPlugins()
    {
        $commands = [
            // Go to site root
            'cd $FORGE_SITE_PATH/public',

            // Delete plugins
            'wp plugin delete akismet',
            'wp plugin delete hello',

            // Install WP Migrate
            'wp plugin install https://downloads.lamapress.nl/wp-migrate-db-pro.zip --activate',
            'wp_migrate_license_key='.$this->getMigrateDbLicenseKey(),
            'wp migratedb setting update license $wp_migrate_license_key --user='.$this->wpUserEmail(),
            'wp migratedb setting update push on',

            // Update all plugins
            'wp plugin update --all',
        ];
        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Install WordPress theme
     */
    private function installTheme()
    {
        $themeFolderName = explode('/', $this->repo)[1];

        $commands = [
            // Go to themes folder
            'cd $FORGE_SITE_PATH/public/wp-content/themes',

            // Clone project theme
            'git clone --depth=1 git@github.com:lamalamaNL/'.$themeFolderName.'.git '.$themeFolderName,
            'wp theme activate '.$themeFolderName,

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

        return $siteCommand;
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

        return $this->db_password = Str::random(16);
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
        return rtrim($this->site->directory, '/');
    }

    /**
     * Get the site ID
     */
    private function siteId()
    {
        return $this->site->id;
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

        return $this->wpUserEmail = 'wordpress@lamalama.nl';
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
    private function setBuildScriptAndDeploy()
    {
        $themeFolderName = explode('/', $this->repo)[1];

        $commands = [
            // Go to theme folder
            'cd $FORGE_SITE_PATH/public/wp-content/themes/'.$themeFolderName,

            // Install dependencies
            'npm install',

            // Build theme
            'npm run build',
        ];

        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    /**
     * Set the final deployment script
     */
    private function setFinalDeploymentScript()
    {
        $themeFolderName = explode('/', $this->repo)[1];

        $commands = [
            // Go to theme folder
            'cd $FORGE_SITE_PATH/public/wp-content/themes/'.$themeFolderName,

            // Reset hard to origin branch
            'git reset --hard origin/$FORGE_SITE_BRANCH',

            // Pull origin branch
            'git pull origin $FORGE_SITE_BRANCH',

            // Install dependencies
            'npm install',

            // Build theme
            'npm run build',

            // Restart FPM
            'echo \'Restarting FPM...\'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock',
        ];

        $command = implode("\n", $commands);

        $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
    }

    /**
     * Get the Migrate DB connection key
     */
    private function getMigrateDbConnectionKey()
    {
        $command = ['command' => 'cd public && wp migratedb setting get connection-key'];

        try {
            $result = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $command);

            $siteCommand = $result;

            // Wait for command to complete
            while ($siteCommand->status === 'running' || $siteCommand->status === 'waiting') {
                sleep(1);
                $result = $this->forge->getSiteCommand($this->serverId, $this->siteId(), $siteCommand->id);
                $siteCommand = $result[0];
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
