<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Arr;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use LamaLama\Clli\Console\Services\CliConfig;
use Laravel\Forge\Exceptions\ValidationException;
use Laravel\Forge\Forge;
use Laravel\Forge\Resources\Site;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
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
    protected string $serverId = '525126';
    private ?string $db_password = null;
    private ?string $db_name = null;
    private ?string $db_user = null;
    private ?string $siteIsolatedName = null;
    private ?string $ip = null;
    private ?string $wpUser = null;
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
            ->setDescription('Create a staging environment and install wordpress for this project on a forge server.');
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

//        if (! $input->getArgument('key')) {
//            $input->setArgument('key', text(
//                label: 'What is the key of your Figma file?',
//                placeholder: 'E.g. 6sftwMKT80KxNnVjS9cZcX',
//                required: 'The file key is required.',
//                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
//                    ? 'The key may only contain letters, numbers, dashes, underscores, and periods.'
//                    : null,
//            ));
//        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {


        $this->cfg = new CliConfig();
        $this->forge = new Forge($this->getForgeToken());
        $this->subdomain = $this->getSubdomain();

        # Setup the site
        $sites = $this->forge->sites($this->serverId);
        //TODO: Validate the site does not exist

//        spin(fn() => $this->createSite(), 'Creating site');
//        spin(fn() => $this->createDatabase(), 'Creating database');
//        spin(fn() => $this->installSsl(), 'Installing SSL certificate');
//        spin(fn() => $this->installSsh(), 'Installing your SSH key');
//        spin(fn() => $this->installEmptyRepo(), 'Installing empty repo');
        spin(fn() => $this->installWordpress(), 'Installing wordpress');
        spin(fn() => $this->migrateLocalDatabase(), 'Migrating local database to staging');
        spin(fn() => $this->setDeployscriptAndDeploy(), 'Deploying project');
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

    private function createSite()
    {
        $config = [
            "domain" => $this->fullDomain(),
            "project_type" => "php",
            "aliases" => [],
            "directory" => "/public",
            "isolated" => true,
            "username" => $this->siteIsolatedName(),
//            "database" => "site_com_db",
            "php_version" => "php81"
        ];

        try {
            $this->site = $this->forge->createSite($this->serverId, $config);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn($er, $field) => "$field: " . Arr::first($er))->implode(' :: '));
            die();
        }
        return $this->site;
    }

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
            $this->output->writeln(collect($e->errors())->map(fn($er, $field) => "$field: " . Arr::first($er))->implode(' :: '));
            die();
        }
    }

    private function installSsl()
    {
        try {
            return $this->forge->obtainLetsEncryptCertificate($this->serverId, $this->siteId(), ['domains' => [$this->fullDomain()]], true);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn($er, $field) => "$field: " . Arr::first($er))->implode(' :: '));
            die();
        }
    }

    private function installWordpress()
    {

        $name = 'dorstenlesser';

        $commands = [


            // Install WordPress
            'cd /home/siteuser_devdaglamalamadev/devdag.lamalama.dev/public',
            'wp core download',
            'wp config create --dbname="'.$this->dbName().'" --dbuser="'.$this->dbUsername().'" --dbpass="'.$this->dbPassword().'" --dbhost="127.0.0.1" --dbprefix=wp_',
            'wp core install --url="http://'.$name.'.test" --title="'.ucfirst($this->wpUser()).'" --admin_user="'.$this->wpUser().'" --admin_password="'.$this->wpPassword().'" --admin_email="'.$this->wpUserEmail().'"',

            // Delete plugins
            'wp plugin delete akismet',
            'wp plugin delete hello',

            // Install plugins and activate
            'wp plugin install https://downloads.lamapress.nl/wp-migrate-db-pro.zip --activate',
            'wp plugin update --all',

            // Clone Lamapress WP boilerplate
            'cd wp-content/themes',
            'git clone --depth=1 git@github.com:lamalamaNL/'.$name.'.git '.$name,
            'wp theme activate '.$name,

            // Delete default themes
            'wp theme delete twentytwentytwo',
            'wp theme delete twentytwentythree',
            'wp theme delete twentytwentyfour',

            // Go to theme folder
//            'cd '.$name,

            // Build
            // 'npm install',
            // 'npm run build',

            // Migrate
//            'wp_migrate_license_key=$(jq -r \'.wp_migrate_license_key\' ~/.clli/config.json)',
//            'wp migrate setting update license $wp_migrate_license_key --user='.$email,
//            'wp migrate pull '.$connectionInfo.' \
//                --find='.$domain.' \
//                --replace=http://'.$name.'.test \
//                --media=all \
//                --plugin-files=all'
//            'cd '.$this->fullDomain(),
        ];


        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
//        $this->runCommandViaApi( ['command' => collect($commands)->implode(' && ')]);



    }

    private function runCommandViaApi($command)
    {
        $this->output->writeln('Run command: ' . $command['command']);
        $siteCommand = $this->forge->executeSiteCommand($this->serverId, $this->siteId(), $command);
        var_dump($siteCommand->status);
        var_dump($siteCommand->output);
        foreach ($siteCommand->output as $line) {
            $this->output->writeln('Result: ' . $line);
        }

    }

    private function runCommandViaDeployScript($command)
    {
        $this->output->writeln('Run command: ' . $command);
        $this->forge->updateSiteDeploymentScript($this->serverId, $this->siteId(), $command);
        $this->forge->deploySite($this->serverId, $this->siteId());
    }



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

    private function getSubdomain()
    {
        $subdomain = $this->input->getArgument('subdomain');
        if ($subdomain) {
            return $subdomain;
        }
        return text(label: 'What is the subdomain we need to deploy to', required: true);

    }

    private function fullDomain()
    {
        return $this->subdomain . ".lamalama.dev";
    }

    private function dbName()
    {
        if($this->db_name) {
            return $this->db_name;
        }
        return $this->db_name = Str::slug('db_' . $this->fullDomain(), '_');
    }

    private function dbUsername()
    {
        if($this->db_user) {
            return $this->db_user;
        }
        return $this->db_user = Str::slug('db_user_' . $this->fullDomain(), '_');
    }

    private function dbPassword()
    {
        if ($this->db_password) {
            return $this->db_password;
        }
        return $this->db_password = 'Edb1ZQvLR2mfLj';
//        return $this->db_password = Str::random(14);
    }

    private function siteIsolatedName()
    {
        if($this->siteIsolatedName) {
            return $this->siteIsolatedName;
        }

        return $this->siteIsolatedName = Str::slug('siteuser_' . $this->fullDomain(), '_');
    }

    private function siteId()
    {
        if($this->site) {
            return $this->site->id;
        }

        return '2365049';
    }

    private function serverIp()
    {
        if ($this->ip) {
            return $this->ip;
        }
        return $this->ip = $this->forge->server($this->serverId)->ipAddress;
    }

    private function getPublicKey(): ?string
    {
        $ssh_key_path = getenv("HOME") . '/.ssh/id_rsa.pub';

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
            echo "Error: SSH key file not found at " . $ssh_key_path;
            return null;
        }

    }

    private function wpUser()
    {
        if($this->wpUser) {
            return $this->wpUser;
        }

        return $this->wpUser = 'Edwin';
    }

    private function wpPassword()
    {
        if($this->wpPassword) {
            return $this->wpPassword;
        }

        return $this->wpPassword = Str::random(9);
    }

    private function wpUserEmail()
    {
        if($this->wpUserEmail) {
            return $this->wpUserEmail;
        }

        return $this->wpUserEmail = 'edwin@lamalama.nl';
    }

    private function getPrivateKey(): ?string
    {
        $ssh_key_path = getenv("HOME") . '/.ssh/id_rsa';

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
            echo "Error: SSH key file not found at " . $ssh_key_path;
            return null;
        }

    }

    private function installSsh()
    {
        $key = $this->getPublicKey();
        if(!$key) {
            die('Could not get your public SSH key.');
        }
        $payload = [
            "name" => "clli_added_key_" . Str::random('8'),
            "key" => $key,
            "username" => $this->siteIsolatedName()
        ];
        var_dump($payload);
        try {
            $this->forge->createSSHKey($this->serverId, $payload);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn($er, $field) => "$field: " . Arr::first($er))->implode(' :: '));
            die();
        }
    }

    private function installEmptyRepo()
    {
        $payload = [
            "provider" => "github",
            "repository" => "lamalamaNL/empty",
            "branch" => "main",
            "composer" => false
        ];
        try {
            $this->forge->installGitRepositoryOnSite($this->serverId, $this->siteId(), $payload);
        } catch (ValidationException $e) {
            $this->output->writeln('Validation error');
            $this->output->writeln(collect($e->errors())->map(fn($er, $field) => "$field: " . Arr::first($er))->implode(' :: '));
            die();
        }

    }

    private function setDeployscriptAndDeploy()
    {
        $commands = [
            'cd /home/siteuser_devdaglamalamadev/devdag.lamalama.dev/public/wp-content/themes',
            'npm install',
            'npm run build',
        ];

        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }

    private function migrateLocalDatabase()
    {
        $migrateKey = exec('wp migrate setting get connection-key');
        $commands = [
          "wp migratedb pull https://".$this->fullDomain()." $migrateKey --media=all --plugin-files=all"
        ];
        $this->runCommandViaDeployScript(collect($commands)->implode(' && '));
    }


}
