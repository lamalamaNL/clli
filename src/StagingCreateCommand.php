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

use function Laravel\Prompts\alert;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

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

    private ?int $organizationId = null;

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

        intro('Lama Lama CLLI - Create Staging Environment');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->cfg = new CliConfig;

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
        $initialStartTime = microtime(true);

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

        $this->displayCredentials();
        $this->displayServerInfo();

        outro('Staging environment created successfully!');

        return Command::SUCCESS;
    }

    /**
     * Initialize Forge client and set required properties for site creation
     */
    private function initializeCommand(): void
    {
        $this->logVerbose('Initializing Forge client...');
        $forgeToken = $this->getForgeToken();
        $this->logVerbose('Forge token retrieved (length: '.strlen($forgeToken).' chars)');

        $this->forge = new Forge($forgeToken);
        $this->forge->setTimeout(300);
        $this->logVerbose('Forge client initialized with 300s timeout');

        $this->serverId = $this->getServerId();
        $this->logVerbose("Server ID: {$this->serverId}");

        $this->subdomain = $this->getSubdomain();
        $this->logVerbose("Subdomain: {$this->subdomain}");

        $this->repo = $this->calulateRepo();
        $this->logVerbose("Repository: {$this->repo}");
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

        return $this->repo = self::GITHUB_ORG.'/'.trim(basename(getcwd()));
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
                    $this->logVerbose('Fetching servers from Forge API...');
                    $this->forge = new Forge($this->getForgeToken());
                    $this->logVerbose('Calling forge->servers()...');
                    $servers = $this->forge->servers();
                    $this->logVerbose('Servers retrieved: '.count($servers));
                    $serverChoices = [];
                    foreach ($servers as $server) {
                        $serverChoices[$server->id] = $server->name;
                        $this->logVerbose("Server: {$server->id} - {$server->name}");
                    }

                    $value = select(
                        label: 'Choose a server',
                        options: $serverChoices,
                        required: true
                    );
                    $this->logVerbose("Selected server ID: {$value}");
                } else {
                    $hint = match ($key) {
                        'forge_token' => 'Get this from https://forge.laravel.com/user/profile#/api',
                        'cloudflare_token' => 'Generate via \'Create Token\' at https://dash.cloudflare.com/profile/api-tokens',
                        'cloudflare_zone_id' => 'Found in the Overview tab of your domain on https://dash.cloudflare.com',
                        'wp_migrate_license_key' => 'Available in your WP Migrate account at https://deliciousbrains.com/my-account/licenses',
                        default => $help,
                    };

                    if (in_array($key, ['forge_token', 'cloudflare_token', 'wp_migrate_license_key'])) {
                        $value = password(
                            label: "CLLI config is missing the $key key. Please provide a value",
                            hint: $hint,
                            required: true
                        );
                    } else {
                        $value = text(
                            label: "CLLI config is missing the $key key. Please provide a value",
                            hint: $hint,
                            required: true
                        );
                    }
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
            error('Theme folder not found. Run this command from the theme folder.');
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

        // Add organization ID (required for organizations with multiple owners)
        // The legacy API requires organization_id when an organization has multiple owners
        $organizationId = $this->getOrganizationId();
        if ($organizationId) {
            $config['organization_id'] = $organizationId;
            $this->logVerbose("Organization ID: {$organizationId}");
        } else {
            $this->logVerbose('WARNING: No organization ID found - attempting to create site without it');
            $this->logVerbose('If your organization has multiple owners, this will fail');
        }

        $this->logVerbose('Creating site with config:');
        $this->logVerbose(json_encode($config, JSON_PRETTY_PRINT));
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose('Organization ID in config: '.($config['organization_id'] ?? 'NOT SET'));

        try {
            $this->logVerbose('Calling forge->createSite()...');
            $this->logVerbose('Request payload that will be sent:');
            $this->logVerbose(json_encode($config, JSON_PRETTY_PRINT));
            $this->site = $this->forge->createSite($this->serverId, $config);
            $this->logVerbose('Site created successfully!');
            $this->logVerbose("Site ID: {$this->site->id}");
            $this->logVerbose("Site Name: {$this->site->name}");
            $this->logVerbose('Site Status: '.($this->site->status ?? 'N/A'));
            $this->logVerbose('Site Directory: '.($this->site->directory ?? 'N/A'));
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));

            // Check if the error is about organization
            $errors = $e->errors();
            if (isset($errors['organization']) && ! $organizationId) {
                warning('Your organization has multiple owners and requires an organization ID.');
                alert('The organization ID could not be automatically detected.');
                info('');
                info('Please set your organization ID manually:');
                info('  clli config:update forge_organization_id');
                info('');
                info('You can find your organization ID in the Forge dashboard URL:');
                info('  https://forge.laravel.com/organizations/{organization_id}/servers');
                exit(1);
            }

            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }

        return $this->site;
    }

    /**
     * Create MySQL database and user for the WordPress installation
     */
    private function createDatabase(): void
    {
        $dbConfig = [
            'name' => $this->dbName(),
            'user' => $this->dbUsername(),
            'password' => $this->dbPassword(),
        ];

        $this->logVerbose('Creating database with config:');
        $this->logVerbose(json_encode(array_merge($dbConfig, ['password' => '***REDACTED***']), JSON_PRETTY_PRINT));
        $this->logVerbose("Server ID: {$this->serverId}");

        try {
            $this->logVerbose('Calling forge->createDatabase()...');
            $result = $this->forge->createDatabase($this->serverId, $dbConfig);
            $this->logVerbose('Database created successfully!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
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
            $cfToken = password(
                label: 'We need a Cloudflare API token for DNS updates. Please provide your token:',
                hint: 'Generate via \'Create Token\' at https://dash.cloudflare.com/profile/api-tokens',
                required: true
            );
            $this->cfg->set('cloudflare_token', $cfToken);
        }

        $cfZoneId = $this->cfg->get('cloudflare_zone_id');
        if (! $cfZoneId) {
            $cfZoneId = text(
                label: 'We need your Cloudflare Zone ID for lamalama.dev:',
                hint: 'Found in the Overview tab of your domain on https://dash.cloudflare.com',
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

                warning('Existing DNS record updated');
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
        $sslConfig = ['domains' => [$this->fullDomain()]];
        $this->logVerbose('Installing SSL certificate...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");
        $this->logVerbose('SSL config: '.json_encode($sslConfig, JSON_PRETTY_PRINT));

        try {
            $this->logVerbose('Calling forge->obtainLetsEncryptCertificate()...');
            $result = $this->forge->obtainLetsEncryptCertificate($this->serverId, $this->siteId(), $sslConfig, true);
            $this->logVerbose('SSL certificate installation initiated!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));

            return $result;
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));
            throw new \RuntimeException('Error installing SSL: '.$e->getMessage());
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Download and configure fresh WordPress installation
     */
    private function installWordPress()
    {
        $this->logVerbose('Installing WordPress...');
        $themeFolderName = explode('/', $this->repo)[1];
        $this->logVerbose("Theme folder name: {$themeFolderName}");

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

        $commandString = collect($commands)->implode(' && ');
        $this->logVerbose("WordPress installation commands:\n{$commandString}");
        $this->runCommandViaDeployScript($commandString);
    }

    /**
     * Install and configure required WordPress plugins
     */
    private function installPlugins()
    {
        $this->logVerbose('Installing WordPress plugins...');

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

            // Update WP Migrate
            'wp plugin update --all',

            // Empty caches
            'wp cache flush',
            'wp rewrite flush',

            // Reactivate WP Migrate
            'wp plugin deactivate wp-migrate-db-pro',
            'wp plugin activate wp-migrate-db-pro',
            'wp_migrate_license_key='.$this->getMigrateDbLicenseKey(),
            'wp migratedb setting update license $wp_migrate_license_key --user='.$this->wpUserEmail(),
            'wp migratedb setting update push on',

            '( flock -w 10 9 || exit 1',
            '    echo "Restarting FPM..."; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock',
        ];

        $commandString = collect($commands)->implode(' && ');
        $this->logVerbose("Plugin installation commands:\n{$commandString}");
        $this->runCommandViaDeployScript($commandString);
    }

    /**
     * Clone theme repository and remove default WordPress themes
     */
    private function installTheme()
    {
        $this->logVerbose('Installing theme...');
        $themeFolderName = explode('/', $this->repo)[1];
        $this->logVerbose("Theme folder name: {$themeFolderName}");
        $this->logVerbose("Repository: {$this->repo}");
        $this->logVerbose('Branch: '.self::DEFAULT_BRANCH);

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

        $commandString = collect($commands)->implode(' && ');
        $this->logVerbose("Theme installation commands:\n{$commandString}");
        $this->runCommandViaDeployScript($commandString);
    }

    /**
     * Run a command via the Forge API
     */
    private function runCommandViaApi(string $command): mixed
    {
        $this->logVerbose('Executing site command via API...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");
        $this->logVerbose("Command: {$command}");

        try {
            $this->logVerbose('Calling forge->executeSiteCommand()...');
            $siteCommand = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), ['command' => $command]);
            $this->logVerbose('Command executed successfully!');
            $this->logVerbose('Command ID: '.($siteCommand->id ?? 'N/A'));
            $this->logVerbose('Command Status: '.($siteCommand->status ?? 'N/A'));
            $this->logVerbose('Full response: '.json_encode($siteCommand, JSON_PRETTY_PRINT));

            return $siteCommand;
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Run a command via the deployment script
     */
    private function runCommandViaDeployScript(string $command): mixed
    {
        $this->logVerbose('Running command via deployment script...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");
        $this->logVerbose("Command: {$command}");

        try {
            $this->logVerbose('Calling forge->updateSiteDeploymentScript()...');
            $updateResult = $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
            $this->logVerbose('Deployment script updated!');
            $this->logVerbose('Update result: '.json_encode($updateResult, JSON_PRETTY_PRINT));

            $this->logVerbose('Calling forge->deploySite()...');
            $result = $this->forge->deploySite($this->serverId, $this->siteId());
            $this->logVerbose('Deployment initiated!');
            $this->logVerbose('Deploy result: '.json_encode($result, JSON_PRETTY_PRINT));

            return $result;
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
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
        $serverId = text(
            label: 'We need a forge server ID for this command. Please provide a forge server ID',
            hint: 'You can find this in your Forge dashboard URL or by listing servers',
            required: true
        );
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
        $forgeToken = password(
            label: 'We need a forge token for this command. Please provide a forge token',
            hint: 'Get this from https://forge.laravel.com/user/profile#/api',
            required: true
        );
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
        $migrateDbLicenseKey = password(
            label: 'We need a Migrate DB license key for this command. Please provide a Migrate DB license key',
            hint: 'Available in your WP Migrate account at https://deliciousbrains.com/my-account/licenses',
            required: true
        );
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

        return text(
            label: 'What is the subdomain we need to deploy to',
            placeholder: 'E.g. projectname',
            hint: 'This will create: projectname.lamalama.dev',
            required: true
        );
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
     * Get the organization ID from the server or organizations API
     */
    private function getOrganizationId(): ?int
    {
        if ($this->organizationId !== null) {
            return $this->organizationId;
        }

        // First check if it's stored in config
        $orgId = $this->cfg->get('forge_organization_id');
        if ($orgId) {
            $this->logVerbose("Organization ID from config: {$orgId}");

            return $this->organizationId = (int) $orgId;
        }

        // Fetch from server
        $this->logVerbose('Fetching organization ID from server...');
        $this->logVerbose("Server ID: {$this->serverId}");

        try {
            $this->logVerbose('Calling forge->server()...');
            $server = $this->forge->server($this->serverId);
            $this->logVerbose('Server retrieved!');
            $this->logVerbose('Server data: '.json_encode($server, JSON_PRETTY_PRINT));

            // Check for organization_id property (may be named differently)
            $orgId = $server->organizationId ?? $server->organization_id ?? null;

            if ($orgId) {
                $this->logVerbose("Organization ID from server: {$orgId}");
                $this->cfg->set('forge_organization_id', $orgId);

                return $this->organizationId = (int) $orgId;
            }

            $this->logVerbose('No organization ID found on server object, trying to fetch organizations...');

            // Try to fetch organizations from API - check multiple possible method names
            $organizations = null;
            if (method_exists($this->forge, 'organizations')) {
                try {
                    $this->logVerbose('Calling forge->organizations()...');
                    $organizations = $this->forge->organizations();
                } catch (\Exception $e) {
                    $this->logVerbose('Error calling organizations(): '.$e->getMessage());
                }
            }

            // Try alternative method name
            if (! $organizations && method_exists($this->forge, 'getOrganizations')) {
                try {
                    $this->logVerbose('Calling forge->getOrganizations()...');
                    $organizations = $this->forge->getOrganizations();
                } catch (\Exception $e) {
                    $this->logVerbose('Error calling getOrganizations(): '.$e->getMessage());
                }
            }

            if ($organizations && is_array($organizations)) {
                $this->logVerbose('Organizations retrieved: '.count($organizations));

                if (count($organizations) === 1) {
                    $orgId = $organizations[0]->id ?? $organizations[0]->organizationId ?? $organizations[0]->organization_id ?? null;
                    if ($orgId) {
                        $this->logVerbose("Organization ID from organizations API: {$orgId}");
                        $this->cfg->set('forge_organization_id', $orgId);

                        return $this->organizationId = (int) $orgId;
                    }
                } elseif (count($organizations) > 1) {
                    // Multiple organizations - let user select
                    $this->logVerbose('Multiple organizations found, prompting user to select...');
                    $orgChoices = [];
                    foreach ($organizations as $org) {
                        $orgId = $org->id ?? $org->organizationId ?? $org->organization_id ?? null;
                        $orgName = $org->name ?? $org->organizationName ?? "Organization {$orgId}";
                        if ($orgId) {
                            $orgChoices[$orgId] = $orgName;
                            $this->logVerbose("Organization: {$orgId} - {$orgName}");
                        }
                    }

                    if (! empty($orgChoices)) {
                        $selectedOrgId = select(
                            label: 'Your organization has multiple owners. Please select an organization:',
                            options: $orgChoices,
                            required: true
                        );

                        $this->logVerbose("Selected organization ID: {$selectedOrgId}");
                        $this->cfg->set('forge_organization_id', $selectedOrgId);

                        return $this->organizationId = (int) $selectedOrgId;
                    }
                }
            } else {
                $this->logVerbose('Could not fetch organizations from API - method may not exist or returned null');
            }

            // If we still don't have an organization ID, prompt the user
            $this->logVerbose('No organization ID found - prompting user to provide it');
            warning('Your organization has multiple owners, but the organization ID could not be automatically detected.');
            info('You can find your organization ID in the Forge dashboard URL when viewing an organization.');
            info('Example: https://forge.laravel.com/organizations/{organization_id}/servers');

            $manualOrgId = text(
                label: 'Please enter your organization ID (or press Enter to skip and try without it):',
                placeholder: 'E.g. 12345',
                hint: 'Found in the Forge dashboard URL',
                required: false
            );

            if ($manualOrgId) {
                $this->logVerbose("Organization ID provided manually: {$manualOrgId}");
                $this->cfg->set('forge_organization_id', $manualOrgId);

                return $this->organizationId = (int) $manualOrgId;
            }

            $this->logVerbose('No organization ID provided - will attempt without it');

            return null;
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());

            // If there's an error, still prompt the user
            warning('Error fetching organization ID: '.$e->getMessage());
            $manualOrgId = text(
                label: 'Please enter your organization ID manually (or press Enter to skip):',
                placeholder: 'E.g. 12345',
                hint: 'Found in the Forge dashboard URL',
                required: false
            );

            if ($manualOrgId) {
                $this->logVerbose("Organization ID provided manually after error: {$manualOrgId}");
                $this->cfg->set('forge_organization_id', $manualOrgId);

                return $this->organizationId = (int) $manualOrgId;
            }

            return null;
        }
    }

    /**
     * Get the server IP address
     */
    private function serverIp(): string
    {
        if ($this->ip) {
            return $this->ip;
        }

        $this->logVerbose('Fetching server IP address...');
        $this->logVerbose("Server ID: {$this->serverId}");

        try {
            $this->logVerbose('Calling forge->server()...');
            $server = $this->forge->server($this->serverId);
            $this->logVerbose('Server retrieved!');
            $this->logVerbose('Server data: '.json_encode($server, JSON_PRETTY_PRINT));
            $ipAddress = $server?->ipAddress;
            $this->logVerbose("IP Address: {$ipAddress}");

            return $this->ip = $ipAddress;
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get the public SSH key
     */
    private function getPublicKey(): ?string
    {
        $publicKeyFilename = $this->cfg->get('public_key_filename');

        if (! $publicKeyFilename) {
            $publicKeyFilename = text(
                label: 'We need a public SSH key filename for this command. Please provide a public key filename',
                placeholder: 'E.g. id_rsa',
                hint: 'The filename of your SSH key in ~/.ssh/ (without .pub extension)',
                required: true
            );
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
        $this->logVerbose('Installing SSH key...');
        $key = $this->getPublicKey();
        if (! $key) {
            $this->logVerbose('ERROR: Could not get public SSH key');
            exit('Could not get your public SSH key.');
        }

        $this->logVerbose('Public SSH key retrieved (length: '.strlen($key).' chars)');

        $payload = [
            'name' => self::SSH_KEY_NAME_PREFIX.Str::random('8'),
            'key' => trim($key),
            'username' => $this->siteIsolatedName(),
        ];

        $this->logVerbose('SSH key payload:');
        $this->logVerbose(json_encode(array_merge($payload, ['key' => substr($payload['key'], 0, 50).'...']), JSON_PRETTY_PRINT));
        $this->logVerbose("Server ID: {$this->serverId}");

        try {
            $this->logVerbose('Calling forge->createSSHKey()...');
            $result = $this->forge->createSSHKey($this->serverId, $payload);
            $this->logVerbose('SSH key created successfully!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
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

        $this->logVerbose('Installing empty repository...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");
        $this->logVerbose('Payload: '.json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $this->logVerbose('Calling forge->installGitRepositoryOnSite()...');
            $result = $this->forge->installGitRepositoryOnSite($this->serverId, $this->siteId(), $payload);
            $this->logVerbose('Repository installed successfully!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }

    }

    /**
     * Set deployment script and deploy
     */
    private function setBuildScriptAndDeploy()
    {
        $this->logVerbose('Setting build script and deploying...');
        $themeFolderName = explode('/', $this->repo)[1];
        $this->logVerbose("Theme folder name: {$themeFolderName}");

        $commands = [
            // Go to theme folder
            'cd $FORGE_SITE_PATH/public/wp-content/themes/'.$themeFolderName,

            // Install dependencies
            'npm ci',

            // Build theme
            'npm run build',
        ];

        $commandString = collect($commands)->implode(' && ');
        $this->logVerbose("Build commands:\n{$commandString}");
        $this->runCommandViaDeployScript($commandString);
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
            'npm ci',

            // Build theme
            'npm run build',

            '',

            // Restart FPM
            '( flock -w 10 9 || exit 1',
            'echo \'Restarting FPM...\'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock',
        ];

        $command = implode("\n", $commands);

        $this->logVerbose('Setting final deployment script...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");
        $this->logVerbose("Theme folder: {$themeFolderName}");
        $this->logVerbose("Deployment script:\n{$command}");

        try {
            $this->logVerbose('Calling forge->updateSiteDeploymentScript()...');
            $result = $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
            $this->logVerbose('Deployment script updated successfully!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
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

        $this->logVerbose('Updating git remote...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");
        $this->logVerbose('Payload: '.json_encode($payload, JSON_PRETTY_PRINT));

        try {
            $this->logVerbose('Calling forge->updateSiteGitRepository()...');
            $result = $this->forge->updateSiteGitRepository($this->serverId, $this->siteId(), $payload);
            $this->logVerbose('Git remote updated successfully!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Get the Migrate DB connection key
     */
    private function getMigrateDbConnectionKey(): string
    {
        $this->logVerbose('Getting Migrate DB connection key...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");

        // First, check if WP Migrate DB plugin is installed and activated
        $checkCommand = ['command' => 'cd public && wp plugin is-active wp-migrate-db'];
        $this->logVerbose('Checking if WP Migrate DB plugin is active...');
        $this->logVerbose('Check command: '.json_encode($checkCommand, JSON_PRETTY_PRINT));

        try {
            $this->logVerbose('Calling forge->executeSiteCommand() to check plugin status...');
            $checkResult = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $checkCommand);
            $checkSiteCommand = $checkResult;
            $this->logVerbose("Command ID: {$checkSiteCommand->id}");
            $this->logVerbose("Initial status: {$checkSiteCommand->status}");

            // Wait for command to complete
            $waitCount = 0;
            while ($checkSiteCommand->status === 'running' || $checkSiteCommand->status === 'waiting') {
                $waitCount++;
                $this->logVerbose("Waiting for command to complete... (attempt {$waitCount})");
                sleep(1);
                $this->logVerbose('Calling forge->getSiteCommand() to check status...');
                $result = $this->forge->getSiteCommand($this->serverId, $this->siteId(), $checkSiteCommand->id);
                $checkSiteCommand = $result[0];
                $this->logVerbose("Status: {$checkSiteCommand->status}");
            }

            $this->logVerbose("Final status: {$checkSiteCommand->status}");
            $this->logVerbose('Output: '.($checkSiteCommand->output ?? 'N/A'));

            if ($checkSiteCommand->status === 'finished' && trim($checkSiteCommand->output) !== 'Active') {
                $this->logVerbose('ERROR: WP Migrate DB plugin is not active');
                error('WP Migrate DB plugin is not active on remote site. Please install and activate it first.');
                exit(1);
            }
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            error('Error checking WP Migrate DB plugin status: '.$e->getMessage());
            exit(1);
        }

        $command = ['command' => 'cd public && wp migratedb setting get connection-key'];
        $this->logVerbose('Getting connection key...');
        $this->logVerbose('Command: '.json_encode($command, JSON_PRETTY_PRINT));

        try {
            $this->logVerbose('Calling forge->executeSiteCommand() to get connection key...');
            $result = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $command);

            $siteCommand = $result;
            $this->logVerbose("Command ID: {$siteCommand->id}");
            $this->logVerbose("Initial status: {$siteCommand->status}");

            // Wait for command to complete
            $waitCount = 0;
            while ($siteCommand->status === 'running' || $siteCommand->status === 'waiting') {
                $waitCount++;
                $this->logVerbose("Waiting for command to complete... (attempt {$waitCount})");
                sleep(1);
                $this->logVerbose('Calling forge->getSiteCommand() to check status...');
                $result = $this->forge->getSiteCommand($this->serverId, $this->siteId(), $siteCommand->id);
                $siteCommand = $result[0];
                $this->logVerbose("Status: {$siteCommand->status}");
            }

            $this->logVerbose("Final status: {$siteCommand->status}");
            $this->logVerbose('Output: '.($siteCommand->output ?? 'N/A'));

            if ($siteCommand->status === 'finished') {
                // Extract connection key from output
                $output = trim($siteCommand->output);
                $this->logVerbose('Trimmed output length: '.strlen($output));

                // Check for common error messages
                if (empty($output) || $output === 'Command output not found.' || $output === 'No output') {
                    $this->logVerbose('ERROR: Failed to retrieve connection key');
                    error('Failed to retrieve connection key from remote site.');
                    error('This usually means:');
                    error('1. WP Migrate DB plugin is not properly configured on remote site');
                    error('2. Plugin is not activated or has errors');
                    error('3. Database connection issues on remote site');
                    error('');
                    error('Please check the remote site and ensure WP Migrate DB is working properly.');
                    exit(1);
                }

                // Validate connection key format
                if (strlen($output) >= 30 && strlen($output) <= 50 && preg_match('/^[A-Za-z0-9+\/]+$/', $output)) {
                    $this->logVerbose('Connection key validated successfully!');

                    return $output;
                } else {
                    $this->logVerbose('ERROR: Invalid connection key format');
                    $this->logVerbose('Key length: '.strlen($output));
                    $this->logVerbose('Key matches pattern: '.(preg_match('/^[A-Za-z0-9+\/]+$/', $output) ? 'yes' : 'no'));
                    error('Invalid connection key format received: '.$output);
                    error('Expected: 30-50 characters containing only A-Z, a-z, 0-9, +, and /');
                    error('Raw output: '.json_encode($output));
                    exit(1);
                }
            } else {
                $this->logVerbose("ERROR: Command failed with status: {$siteCommand->status}");
                error('Command failed with status: '.$siteCommand->status);
                error('Output: '.($siteCommand->output ?? 'No output'));
                exit(1);
            }
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            error('Error getting connection key: '.$e->getMessage());
            exit(1);
        }
    }

    /**
     * Migrate the local environment to staging
     */
    private function migrateLocaleToRemote(): mixed
    {
        $this->logVerbose('Starting migration from local to remote...');

        $localUrl = exec('wp option get siteurl');
        $this->logVerbose("Local URL: {$localUrl}");

        $remoteUrl = 'https://'.$this->fullDomain();
        $this->logVerbose("Remote URL: {$remoteUrl}");

        $migrateKey = $this->getMigrateDbConnectionKey();
        $this->logVerbose('Connection key retrieved (length: '.strlen($migrateKey).' chars)');

        // Validate that we have a proper local URL
        if (empty($localUrl) || ! filter_var($localUrl, FILTER_VALIDATE_URL)) {
            $this->logVerbose('ERROR: Invalid local URL');
            error('Invalid local URL detected: '.$localUrl);
            exit(1);
        }

        // Validate remote URL format
        if (! filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
            $this->logVerbose('ERROR: Invalid remote URL format');
            error('Invalid remote URL format: '.$remoteUrl);
            exit(1);
        }

        info("Starting migration from {$localUrl} to {$remoteUrl}");

        $command = "wp migratedb push $remoteUrl ".
            escapeshellarg($migrateKey).
            ' --find='.escapeshellarg($localUrl).
            ' --replace='.escapeshellarg($remoteUrl).
            ' --media=all '.
            ' --plugin-files=all';

        $this->logVerbose('Migration command: '.$command);
        info('Executing command: '.$command);

        $this->logVerbose('Executing migration command...');
        exec($command, $output, $exitCode);
        $this->logVerbose("Exit code: {$exitCode}");
        $this->logVerbose('Output lines: '.count($output));
        $this->logVerbose("Output:\n".implode("\n", $output));

        if ($exitCode !== 0) {
            $this->logVerbose('ERROR: Migration failed');
            error('Migration failed with exit code: '.$exitCode);
            error('Command output: '.implode("\n", $output));

            // Provide specific error guidance based on common issues
            $outputString = implode("\n", $output);
            if (strpos($outputString, 'Version Mismatch') !== false) {
                $this->logVerbose('Detected version mismatch error');
                error('Version mismatch detected between local and remote WP Migrate DB plugins.');
                error('Please update one of the plugins to match the other version.');
                error('Local update: wp plugin update wp-migrate-db');
                error('Remote update: Run wp plugin update wp-migrate-db on the remote site');
            } elseif (strpos($outputString, 'Invalid content verification signature') !== false) {
                $this->logVerbose('Detected connection verification error');
                error('Connection verification failed. Please check:');
                error('1. WP Migrate DB plugin is installed and activated on remote site');
                error('2. Connection key is correct and matches remote site');
                error('3. Remote site is accessible and WP Migrate DB is properly configured');
            } elseif (strpos($outputString, 'Connection refused') !== false) {
                $this->logVerbose('Detected connection refused error');
                error('Connection refused. Please check:');
                error('1. Remote site is accessible');
                error('2. Firewall settings allow connections');
                error('3. SSL certificate is valid');
            }

            exit(1);
        }

        $this->logVerbose('Migration completed successfully!');
        info('Migration completed successfully');

        return implode("\n", $output);
    }

    /**
     * Enable quick deploy
     */
    private function enableQuickDeploy(): mixed
    {
        $this->logVerbose('Enabling quick deploy...');
        $this->logVerbose("Server ID: {$this->serverId}");
        $this->logVerbose("Site ID: {$this->siteId()}");

        try {
            $this->logVerbose('Calling forge->enableQuickDeploy()...');
            $result = $this->forge->enableQuickDeploy($this->serverId, $this->siteId());
            $this->logVerbose('Quick deploy enabled successfully!');
            $this->logVerbose('Result: '.json_encode($result, JSON_PRETTY_PRINT));

            return $result;
        } catch (ValidationException $e) {
            $this->logVerbose('ValidationException caught!');
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception errors:');
            $this->logVerbose(print_r($e->errors(), true));
            error('Validation error');
            error(print_r($e->errors(), true));
            exit();
        } catch (\Exception $e) {
            $this->logVerbose('Exception caught: '.get_class($e));
            $this->logVerbose('Exception message: '.$e->getMessage());
            $this->logVerbose('Exception trace: '.$e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Display the WordPress admin credentials to the user.
     */
    private function displayCredentials(): void
    {
        info('');
        info('Staging site ready on [https://'.$this->fullDomain().']. Build something unexpected.');
        info('Admin ready on [https://'.$this->fullDomain().'/wp-admin]. Manage your website here.');
    }

    /**
     * Display the server info to the user.
     */
    private function displayServerInfo(): void
    {
        $output = [
            ['site domain: ', $this->fullDomain()],
            ['server username: ', $this->siteIsolatedName()],
            ['Site id: ', $this->siteId()],
            ['DB name', $this->dbName()],
            ['DB username', $this->dbUsername()],
            ['DB password', $this->dbPassword()],
        ];

        table(['Key', 'Value'], $output);
    }

    /**
     * Log verbose messages if verbose mode is enabled
     */
    private function logVerbose(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln("<fg=gray>[VERBOSE]</> {$message}");
        }
    }
}
