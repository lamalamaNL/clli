<?php

namespace LamaLama\Clli\Console;

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
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class StagingCreateCommand extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    private const DOMAIN_SUFFIX = 'lamalama.dev';

    private const DEFAULT_WP_ADMIN_USER = 'Lama Lama';

    private const DEFAULT_WP_ADMIN_EMAIL = 'wordpress@lamalama.nl';

    private const GITHUB_ORG = 'lamalamaNL';

    private const EMPTY_REPO = 'empty';

    private const DEFAULT_BRANCH = 'develop';

    private const SSH_KEY_EMAIL = 'clli@lamalama.nl';

    private const DB_PREFIX = 'db_';

    private const DB_USER_PREFIX = 'dbu_';

    private const SITE_USER_PREFIX = 'u_';

    private const SSH_KEY_NAME_PREFIX = 'clli_added_key_';

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
            ['checkThemeFolder', 'Theme folder check'],
            ['initializeCommand', 'Initializing command'],
            ['createSite', 'Creating site'],
            ['createDatabase', 'Creating database'],
            ['updateCloudflareDns', 'Updating Cloudflare DNS'],
            ['installSsl', 'Installing SSL certificate'],
            ['installSsh', 'Installing your SSH key'],
            ['installEmptyRepo', 'Installing empty repository'],
            ['installWordPress', 'Installing WordPress'],
            ['installPlugins', 'Installing plugins'],
            ['installTheme', 'Installing theme'],
            ['migrateLocaleToRemote', 'Migrating local to remote'],
            ['setBuildScriptAndDeploy', 'Building project'],
            ['setFinalDeploymentScript', 'Setting final deployment script'],
            ['updateGitRemote', 'Updating git remote'],
            ['enableQuickDeploy', 'Enabling quick deploy'],
        ];

        $totalSteps = count($steps);

        foreach ($steps as $index => [$method, $message]) {
            $startTime = microtime(true);
            $message = 'Step '.($index + 1).'/'.$totalSteps.': '.$message;

            spin(
                message: $message,
                callback: fn () => $this->$method(),
            );

            info("✅ $message completed (".round(microtime(true) - $startTime, 2).'s)', false);
        }

        info('🎉 All done in '.round(microtime(true) - $initialStartTime, 2).'s');

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
     * Initialize Forge client and set required properties for site creation
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
     * Determine repository name from git remote or current directory
     */
    private function calulateRepo(): string
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
     * Validate and prompt for missing CLLI configuration values
     */
    private function checkClliConfig(): bool
    {
        $requiredKeys = [
            'forge_token' => 'Get this from https://forge.laravel.com/user/profile#/api',
            'forge_server_id' => 'Select a server from your Forge account',
            'cloudflare_token' => 'Generate via \'Create Token\' at https://dash.cloudflare.com/profile/api-tokens',
            'cloudflare_zone_id' => 'Found in the Overview tab of your domain on https://dash.cloudflare.com',
            'wp_migrate_license_key' => 'Available in your WP Migrate account at https://deliciousbrains.com/my-account/licenses',
        ];

        foreach ($requiredKeys as $key => $help) {
            if (! $this->cfg->get($key)) {
                info($help);

                if ($key === 'forge_server_id') {
                    $this->forge = new Forge($this->getForgeToken());
                    $servers = $this->forge->servers();
                    $serverChoices = [];
                    foreach ($servers as $server) {
                        $serverChoices[$server->id] = $server->name;
                    }

                    $value = select(
                        label: 'Choose a server',
                        options: $serverChoices,
                        required: true
                    );
                } else {
                    $value = text(
                        label: "CLLI config is missing the $key key. Please provide a value",
                        required: true
                    );
                }
                $this->cfg->set($key, $value);
            }
        }

        return true;
    }

    /**
     * Verify command is run from within a WordPress theme directory
     */
    private function checkThemeFolder(): bool
    {
        if (! str_contains(getcwd(), 'wp-content/themes/')) {
            error('⚠️  Theme folder not found, run this command from the theme folder');
            exit(1);
        }

        return true;
    }

    /**
     * Create new isolated PHP site on Forge with specified configuration
     */
    private function createSite(): Site
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
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        }

        return $this->site;
    }

    /**
     * Create MySQL database and user for the WordPress installation
     */
    private function createDatabase(): void
    {
        try {
            $this->forge->createDatabase($this->serverId, [
                'name' => $this->dbName(),
                'user' => $this->dbUsername(),
                'password' => $this->dbPassword(),
            ]);
        } catch (ValidationException $e) {
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        }
    }

    /**
     * Add DNS A record in Cloudflare pointing subdomain to server IP
     */
    public function updateCloudflareDns(): bool
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

            // Check if record exists first
            $records = $dns->listRecords($cfZoneId, 'A', $this->subdomain.'.'.self::DOMAIN_SUFFIX);

            if (! empty($records->result)) {
                // Update existing record
                $recordId = $records->result[0]->id;
                $result = $dns->updateRecordDetails(
                    $cfZoneId,
                    $recordId,
                    [
                        'type' => 'A',
                        'name' => $this->subdomain.'.'.self::DOMAIN_SUFFIX,
                        'content' => $this->serverIp(),
                        'ttl' => 0,
                        'proxied' => false,
                    ]
                );

                info('⚠️ Existing DNS record updated');
            } else {
                // Create new record
                $result = $dns->addRecord(
                    $cfZoneId,
                    'A',
                    $this->subdomain,
                    $this->serverIp(),
                    0, // Auto TTL
                    false // Proxied through Cloudflare
                );
            }

            if (! $result) {
                throw new \RuntimeException('Failed to create/update DNS record');
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Error updating DNS: '.$e->getMessage());
        }

        return true;
    }

    /**
     * Request and install Let's Encrypt SSL certificate
     */
    private function installSsl(): mixed
    {
        try {
            return $this->forge->obtainLetsEncryptCertificate($this->serverId, $this->siteId(), ['domains' => [$this->fullDomain()]], true);
        } catch (ValidationException $e) {
            throw new \RuntimeException('Error installing SSL: '.$e->getMessage());
        }
    }

    /**
     * Download and configure fresh WordPress installation
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
     * Install and configure required WordPress plugins
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
     * Clone theme repository and remove default WordPress themes
     */
    private function installTheme()
    {
        $themeFolderName = explode('/', $this->repo)[1];

        $commands = [
            // Go to themes folder
            'cd $FORGE_SITE_PATH/public/wp-content/themes',

            // Clone project theme
            'git clone --depth=1 -b '.self::DEFAULT_BRANCH.' git@github.com:'.self::GITHUB_ORG.'/'.$themeFolderName.'.git '.$themeFolderName,
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
    private function runCommandViaApi(array $command): mixed
    {
        $siteCommand = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $command);

        return $siteCommand;
    }

    /**
     * Run a command via the deployment script
     */
    private function runCommandViaDeployScript(string $command): mixed
    {
        $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
        $result = $this->forge->deploySite($this->serverId, $this->siteId());

        return $result;
    }

    /**
     * Get the server ID
     */
    private function getServerId(): string
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
    private function getForgeToken(): string
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
    private function getMigrateDbLicenseKey(): string
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
    private function getSubdomain(): string
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
    private function fullDomain(): string
    {
        return $this->subdomain.'.'.self::DOMAIN_SUFFIX;
    }

    /**
     * Get the database name
     */
    private function dbName(): string
    {
        if ($this->db_name) {
            return $this->db_name;
        }

        $dbName = substr(Str::slug(self::DB_PREFIX.$this->fullDomain(), '_'), 0, 16);
        $dbName .= '_'.date('YmdHis');

        return $this->db_name = $dbName;
    }

    /**
     * Get the database username
     */
    private function dbUsername(): string
    {
        if ($this->db_user) {
            return $this->db_user;
        }

        $dbUser = substr(Str::slug(self::DB_USER_PREFIX.$this->fullDomain(), '_'), 0, 16);
        $dbUser .= '_'.date('YmdHis');

        return $this->db_user = $dbUser;
    }

    /**
     * Get the database password
     */
    private function dbPassword(): string
    {
        if ($this->db_password) {
            return $this->db_password;
        }

        return $this->db_password = Str::random(32);
    }

    /**
     * Get the site's isolated username
     */
    private function siteIsolatedName(): string
    {
        if ($this->siteIsolatedName) {
            return $this->siteIsolatedName;
        }

        return $this->siteIsolatedName = substr(Str::slug(self::SITE_USER_PREFIX.$this->fullDomain(), '_'), 0, 32);
    }

    /**
     * Get the web directory path
     */
    private function webdir(): string
    {
        return rtrim($this->site->directory, '/');
    }

    /**
     * Get the site ID
     */
    private function siteId(): int
    {
        return $this->site->id;
    }

    /**
     * Get the server IP address
     */
    private function serverIp(): string
    {
        if ($this->ip) {
            return $this->ip;
        }

        return $this->ip = $this->forge->server($this->serverId)?->ipAddress;
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
                $command = "ssh-keygen -t rsa -b 4096 -C '".self::SSH_KEY_EMAIL."' -f ".$ssh_key_path." -N ''";
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
    private function wpUser(): string
    {
        if ($this->wpUser) {
            return $this->wpUser;
        }

        return $this->wpUser = self::DEFAULT_WP_ADMIN_USER;
    }

    /**
     * Get the WordPress admin password
     */
    private function wpPassword(): string
    {
        if ($this->wpPassword) {
            return $this->wpPassword;
        }

        return $this->wpPassword = Str::random(9);
    }

    /**
     * Get the WordPress admin email
     */
    private function wpUserEmail(): string
    {
        if ($this->wpUserEmail) {
            return $this->wpUserEmail;
        }

        return $this->wpUserEmail = self::DEFAULT_WP_ADMIN_EMAIL;
    }

    /**
     * Install SSH key on the server
     */
    private function installSsh(): void
    {
        $key = $this->getPublicKey();
        if (! $key) {
            exit('Could not get your public SSH key.');
        }

        $payload = [
            'name' => self::SSH_KEY_NAME_PREFIX.Str::random('8'),
            'key' => trim($key),
            'username' => $this->siteIsolatedName(),
        ];

        try {
            $this->forge->createSSHKey($this->serverId, $payload);
        } catch (ValidationException $e) {
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        }
    }

    /**
     * Install empty repository
     */
    private function installEmptyRepo(): void
    {
        $payload = [
            'provider' => 'github',
            'repository' => self::GITHUB_ORG.'/'.self::EMPTY_REPO,
            'branch' => self::DEFAULT_BRANCH,
            'composer' => false,
        ];

        try {
            $this->forge->installGitRepositoryOnSite($this->serverId, $this->siteId(), $payload);
        } catch (ValidationException $e) {
            error('Validation error');
            error(print_r($e->errors(), true));
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
    private function setFinalDeploymentScript(): void
    {
        $themeFolderName = explode('/', $this->repo)[1];

        $commands = [
            // Go to theme folder
            'cd $FORGE_SITE_PATH/public/wp-content/themes/'.$themeFolderName,

            '',

            // Reset hard to origin branch
            'git reset --hard origin/$FORGE_SITE_BRANCH',

            // Pull origin branch
            'git pull origin $FORGE_SITE_BRANCH',

            '',

            // Install dependencies
            'npm install',

            // Build theme
            'npm run build',

            '',

            // Restart FPM
            '( flock -w 10 9 || exit 1',
            'echo \'Restarting FPM...\'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock',
        ];

        $command = implode("\n", $commands);

        $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
    }

    /**
     * Update the git remote
     */
    private function updateGitRemote(): void
    {
        $payload = [
            'provider' => 'github',
            'repository' => $this->repo,
            'branch' => self::DEFAULT_BRANCH,
        ];

        try {
            $this->forge->updateSiteGitRepository($this->serverId, $this->siteId(), $payload);
        } catch (ValidationException $e) {
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        }
    }

    /**
     * Get the Migrate DB connection key
     */
    private function getMigrateDbConnectionKey(): string
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

            error('Failed to get connection key: '.($siteCommand->output ?? 'No output'));
            exit(1);
        } catch (\Exception $e) {
            error('Error getting connection key: '.$e->getMessage());
            exit(1);
        }
    }

    /**
     * Migrate the local environment to staging
     */
    private function migrateLocaleToRemote(): mixed
    {
        $localUrl = exec('wp option get siteurl');
        $remoteUrl = 'https://'.$this->fullDomain();
        $migrateKey = $this->getMigrateDbConnectionKey();

        $command = "wp migratedb push $remoteUrl ".
            escapeshellarg($migrateKey).
            ' --find='.escapeshellarg($localUrl).
            ' --replace='.escapeshellarg($remoteUrl).
            ' --media=all '.
            ' --plugin-files=all';

        info(print_r($command, true));

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            error('Migration failed');
            exit(1);
        }

        return implode("\n", $output);
    }

    /**
     * Enable quick deploy
     */
    private function enableQuickDeploy(): mixed
    {
        try {
            return $this->forge->enableQuickDeploy($this->serverId, $this->siteId());
        } catch (ValidationException $e) {
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        }
    }
}
