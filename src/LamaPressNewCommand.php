<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use LamaLama\Clli\Console\Services\CliConfig;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class LamaPressNewCommand extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    private const DEFAULT_ADMIN_USER = 'lamalama';

    private const DEFAULT_ADMIN_EMAIL = 'wordpress@lamalama.nl';

    private const GITHUB_ORG = 'lamalamaNL';

    private const THEME_BOILERPLATE_REPO = 'https://github.com/lamalamaNL/lamapress.git';

    private const PLUGIN_DOWNLOAD_URL = 'https://downloads.lamapress.nl';

    private const ADMIN_USERS = [
        'lamalama' => 'wordpress@lamalama.nl',
    ];

    private const PLUGINS_TO_DELETE = [
        'akismet',
        'hello',
    ];

    private const PREMIUM_PLUGINS = [
        'advanced-custom-fields-pro.zip',
        'wp-migrate-db-pro.zip',
        'wp-rocket.zip',
    ];

    private const ACTIVE_PLUGINS = [
        'wordpress-seo',
        'acf-content-analysis-for-yoast-seo',
        'classic-editor',
        'intuitive-custom-post-order',
        'user-switching',
        'wp-mail-smtp',
        'tiny-compress-images',
    ];

    private const INACTIVE_PLUGINS = [
        'wordfence',
    ];

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    protected InputInterface $input;

    protected OutputInterface $output;

    protected ?string $name = null;

    protected ?string $directory = null;

    protected ?string $user = null;

    protected ?string $password = null;

    protected ?string $email = null;

    protected ?string $dbName = null;

    /**
     * Initialize the command configuration and set up required arguments and options.
     */
    protected function configure(): void
    {
        $this
            ->setName('lamapress:new')
            ->setDescription('Create a new LamaPress application')
            ->addArgument('name', InputArgument::OPTIONAL);
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
     * Run the command to create a new LamaPress application, executing all setup steps
     * in sequence while displaying progress.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;

        $startTime = microtime(true);
        $initialStartTime = microtime(true);

        $steps = [
            ['initializeCommand', 'Initializing command'],
            ['verifyDirectory', 'Verifying directory'],
            ['createWordPress', 'Creating WordPress installation'],
            ['configureWordPress', 'Configuring WordPress'],
            ['addUsers', 'Adding users'],
            ['installPlugins', 'Installing plugins'],
            ['setupTheme', 'Setting up theme'],
            ['createProjectConfig', 'Creating project config'],
            ['initializeGit', 'Initializing Git repository'],
            ['buildAssets', 'Building assets'],
            ['pushToGitHub', 'Pushing to GitHub'],
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

        $this->displayCredentials();

        return Command::SUCCESS;
    }

    /**
     * Set up initial command configuration and default values.
     */
    private function initializeCommand(): void
    {
        $this->name = $this->input->getArgument('name') ?? text(
            label: 'What is the name of your application?',
            required: true
        );
        $this->directory = $this->name !== '.' ? getcwd().'/'.$this->name : '.';
        $this->user = self::DEFAULT_ADMIN_USER;
        $this->password = md5(time().uniqid());
        $this->email = self::DEFAULT_ADMIN_EMAIL;
        $this->dbName = str_replace('-', '_', strtolower($this->name));
    }

    /**
     * Check if the target directory already exists to prevent overwriting.
     *
     * @throws RuntimeException If the directory already exists
     */
    private function verifyDirectory(): void
    {
        if ((is_dir($this->directory) || is_file($this->directory)) && $this->directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Download and set up a fresh WordPress installation with database configuration.
     */
    private function createWordPress(): void
    {
        $commands = [
            'mkdir -p '.$this->directory,
            'cd '.$this->directory,
            'wp core download',
            'wp config create --dbname="'.$this->dbName.'" --dbuser="root" --dbpass="" --dbhost="127.0.0.1" --dbprefix=wp_',
            'wp db create',
        ];

        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Configure WordPress core settings and initial options.
     */
    private function configureWordPress(): void
    {
        $commands = [
            'cd '.$this->directory,
            'wp core install --url="http://'.$this->name.'.test" --title="'.ucfirst($this->name).'" --admin_user="'.$this->user.'" --admin_password="'.$this->password.'" --admin_email="'.$this->email.'"',

            'wp option update blog_public 0',
            'wp post delete $(wp post list --post_type=post --posts_per_page=1 --post_status=publish --post_name="hello-world" --field=ID)',
            'wp post delete $(wp post list --post_type=page --posts_per_page=1 --post_status=draft --post_name="privacy-policy" --field=ID)',
            'wp post delete $(wp post list --post_type=page --posts_per_page=1 --post_status=publish --post_name="sample-page" --field=ID)',
            'wp post create --post_type=page --post_title="Home" --post_status=publish --post_author=$(wp user get '.$this->user.' --field=ID)',
            'wp option update show_on_front "page"',
            'wp option update page_on_front $(wp post list --post_type=page --post_status=publish --posts_per_page=1 --post_name="home" --field=ID --format=ids)',
            'wp option update timezone_string "Europe/Amsterdam"',
            'wp option update date_format "m/d/Y"',
            'wp option update time_format "H:i"',
            'wp option update start_of_week 1',
            'wp rewrite structure "/%postname%/" --hard',
        ];

        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Add users to the WordPress installation.
     */
    private function addUsers(): void
    {
        $commands = [];
        foreach (self::ADMIN_USERS as $username => $email) {
            $password = md5(time().uniqid());
            $commands[] = 'cd '.$this->directory;
            $commands[] = 'wp user create '.$username.' '.$email.' --role=administrator --user_pass='.$password;
        }
        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Install and configure WordPress plugins.
     */
    private function installPlugins(): void
    {
        // Delete plugins
        foreach (self::PLUGINS_TO_DELETE as $plugin) {
            $commands = [
                'cd '.$this->directory,
                'wp plugin delete '.$plugin,
            ];
            $this->runCommands($commands, $this->input, $this->output);
        }

        // Install and activate regular plugins
        foreach (self::ACTIVE_PLUGINS as $plugin) {
            $commands = [
                'cd '.$this->directory,
                "wp plugin install {$plugin} --activate",
            ];
            $this->runCommands($commands, $this->input, $this->output);
        }

        // Install but don't activate certain plugins
        foreach (self::INACTIVE_PLUGINS as $plugin) {
            $commands = [
                'cd '.$this->directory,
                "wp plugin install {$plugin}",
            ];
            $this->runCommands($commands, $this->input, $this->output);
        }

        // Install premium plugins from custom URL
        foreach (self::PREMIUM_PLUGINS as $plugin) {
            $commands = [
                'cd '.$this->directory,
                'wp plugin install '.self::PLUGIN_DOWNLOAD_URL."/{$plugin} --activate",
            ];
            $this->runCommands($commands, $this->input, $this->output);
        }

        // Set WP Migrate DB Pro license key if available in config
        $config = new CliConfig;
        $licenseKey = $config->get('wp_migrate_license_key');

        if ($licenseKey) {
            $commands = [
                'cd '.$this->directory,
                "wp migrate setting update license {$licenseKey} --user=".$this->email,
            ];
            $this->runCommands($commands, $this->input, $this->output);
        }

        // Update all plugins
        $commands = [
            'cd '.$this->directory,
            'wp plugin update --all',
        ];
        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Set up the WordPress theme by cloning the boilerplate and configuring it.
     */
    private function setupTheme(): void
    {
        $commands = [
            'cd '.$this->directory.'/wp-content/themes',
            'git clone '.self::THEME_BOILERPLATE_REPO.' '.$this->name,
            'cd '.$this->name,

            // Remove TODO.md
            'rm -rf TODO.md',

            // Remove and create README.md
            'rm -rf README.md',
            'echo "![Cover](https://storage.lamalama.nl/lamalama/playheart-cover.jpeg)

# '.ucfirst($this->name).'
## A LamaPress website
" > README.md',

            // Remove and create style.css
            'rm -rf style.css',
            'echo "/*
Theme Name: '.ucfirst($this->name).'
Theme URI: http://'.$this->name.'.test/
Author: Lama Lama
Author URI: https://lamalama.nl/
Version: 1.0
*/
" > style.css',

            // Remove and create .gitignore
            'rm -rf .gitignore',
            'echo "# Ignore
/node_modules
/vendor
/dist
hot
*.log
.DS_Store
" > .gitignore',

            // Remove .git
            'rm -rf .git',

            // Install dependencies
            'composer install',
            'npm install',
        ];

        $this->runCommands($commands, $this->input, $this->output);

        // Activate the theme and delete old themes
        $commands = [
            'cd '.$this->directory,
            'wp theme activate '.$this->name,

            'wp theme delete twentytwentythree',
            'wp theme delete twentytwentyfour',
            'wp theme delete twentytwentyfive',
        ];

        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Initialize Git repository for the project.
     */
    private function initializeGit(): void
    {
        $commands = [
            'cd '.$this->directory.'/wp-content/themes/'.$this->name,
            'git init -b main',
            'git add .',
            'git commit -m "Initial commit"',
            'git push',
            'git checkout -b develop',
            'git push',
        ];

        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Build theme assets using npm.
     */
    private function buildAssets(): void
    {
        $commands = [
            'cd '.$this->directory.'/wp-content/themes/'.$this->name,
            'npm run build',
        ];

        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Create a project config file.
     */
    private function createProjectConfig(): void
    {
        $config = new CliConfig(forProject: true, path: $this->directory.'/wp-content/themes/'.$this->name);
        $config->set('created_at', date('Y-m-d H:i:s'));
        $config->set('updated_at', date('Y-m-d H:i:s'));
    }

    /**
     * Create a private GitHub repository and push the initial codebase.
     * Skips if GitHub CLI is not installed or authenticated.
     */
    private function pushToGitHub(): void
    {
        $process = new Process(['gh', 'auth', 'status']);
        $process->run();

        if (! $process->isSuccessful()) {
            info('whoops');
            $this->output->writeln('  <bg=yellow;fg=black> WARN </> Make sure the "gh" CLI tool is installed and that you\'re authenticated to GitHub. Skipping...'.PHP_EOL);

            return;
        }

        $commands = [
            'cd '.$this->directory.'/wp-content/themes/'.$this->name,
            'gh repo create '.self::GITHUB_ORG."/{$this->name} --source=. --push --private",
        ];

        $this->runCommands($commands, $this->input, $this->output);
    }

    /**
     * Display the WordPress admin credentials to the user.
     */
    private function displayCredentials(): void
    {
        $this->output->writeln('');
        $this->output->writeln("<bg=blue;fg=white> INFO </> LamaPress ready on <options=bold>[http://{$this->name}.test]</>. Build something gezellebel.".PHP_EOL);
        $this->output->writeln("Username: {$this->user}".PHP_EOL);
        $this->output->writeln("Password: {$this->password}".PHP_EOL);
    }
}
