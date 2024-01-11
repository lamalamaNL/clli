<?php

namespace LamaLama\Clli\Console;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\ProcessUtils;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class NewCommand extends Command
{
    use Concerns\ConfiguresPrompts;

    /**
     * The Composer instance.
     *
     * @var \Illuminate\Support\Composer
     */
    protected $composer;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('new')
            ->setDescription('Create a new LamaPress application')
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        $output->write(PHP_EOL.'<fg=white>██       █████  ███    ███  █████  ██████  ██████  ███████ ███████ ███████ 
██      ██   ██ ████  ████ ██   ██ ██   ██ ██   ██ ██      ██      ██      
██      ███████ ██ ████ ██ ███████ ██████  ██████  █████   ███████ ███████ 
██      ██   ██ ██  ██  ██ ██   ██ ██      ██   ██ ██           ██      ██ 
███████ ██   ██ ██      ██ ██   ██ ██      ██   ██ ███████ ███████ ███████ '.PHP_EOL.PHP_EOL);

        if (! $input->getArgument('name')) {
            $input->setArgument('name', text(
                label: 'What is the name of your project?',
                placeholder: 'E.g. amsterdam750',
                required: 'The project name is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The name may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }

        // if (! $input->getOption('git') && $input->getOption('github') === false && Process::fromShellCommandline('git --version')->run() === 0) {
        //     $input->setOption('git', confirm(label: 'Would you like to initialize a Git repository?', default: false));
        // }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $directory = $name !== '.' ? getcwd().'/'.$name : '.';

        $this->composer = new Composer(new Filesystem(), $directory);

        $version = '';

        if (! $input->getOption('force')) {
            $this->verifyApplicationDoesntExist($directory);
        }

        if ($input->getOption('force') && $directory === '.') {
            throw new RuntimeException('Cannot use --force option when using current directory for installation!');
        }

        $user = 'lamalama';
        $password = md5(time().uniqid());
        $email = 'wordpress@lamalama.nl';

        $dbName = str_replace('-', '_', strtolower($name));

        $commands = [
            'mkdir -p '.$directory,
            'cd '.$directory,
            'wp core download',
            'wp config create --dbname="'.$dbName.'" --dbuser="root" --dbpass="" --dbhost="127.0.0.1" --dbprefix=wp_',
            'wp db create',
            'wp core install --url="http://'.$name.'.test" --title="'.ucfirst($name).'" --admin_user="'.$user.'" --admin_password="'.$password.'" --admin_email="'.$email.'"',
            'wp option update blog_public 0',
            'wp post delete $(wp post list --post_type=post --posts_per_page=1 --post_status=publish --post_name="hello-world" --field=ID)',
            'wp post delete $(wp post list --post_type=page --posts_per_page=1 --post_status=draft --post_name="privacy-policy" --field=ID)',
            'wp post delete $(wp post list --post_type=page --posts_per_page=1 --post_status=publish --post_name="sample-page" --field=ID)',
            'wp post create --post_type=page --post_title="Home" --post_status=publish --post_author=$(wp user get '.$user.' --field=ID)',
            'wp option update show_on_front "page"',
            'wp option update page_on_front $(wp post list --post_type=page --post_status=publish --posts_per_page=1 --post_name="home" --field=ID --format=ids)',
            'wp option update timezone_string "Europe/Amsterdam"',
            'wp option update date_format "m/d/Y"',
            'wp option update time_format "H:i"',
            'wp option update start_of_week 1',
            'wp rewrite structure "/%postname%/" --hard',

            # Users
            'wp user create lamalamaMark mark@lamalama.nl --role=administrator',
            'wp user create lamalamaEdwin edwin@lamalama.nl --role=administrator',
            'wp user create lamalamaAuke auke@lamalama.nl --role=administrator',

            # Delete plugins
            'wp plugin delete akismet',
            'wp plugin delete hello',

            # Install plugins and activate
            'wp plugin install https://downloads.lamapress.nl/advanced-custom-fields-pro.zip --activate',
            'wp plugin install wordpress-seo --activate',
            'wp plugin install acf-content-analysis-for-yoast-seo --activate',
            'wp plugin install classic-editor --activate',
            'wp plugin install intuitive-custom-post-order --activate',
            'wp plugin install user-switching --activate',
            'wp plugin install wp-mail-smtp --activate',
            'wp plugin install tiny-compress-images --activate',
            'wp plugin install https://downloads.lamapress.nl/wp-migrate-db-pro.zip --activate',

            # Install plugins and keep deactivated
            'wp plugin install wordfence',
            'wp plugin install http://downloads.lamapress.nl/wp-rocket.zip',

            # Update all plugins
            'wp plugin update --all',

            # Clone Lamapress WP boilerplate
            'cd wp-content/themes',
            'git clone --depth=1 https://github.com/lamalamaNL/lamapress.git '.$name,
            'wp theme activate '.$name,

            # Delete default themes
            'wp theme delete twentytwentytwo',
            'wp theme delete twentytwentythree',
            'wp theme delete twentytwentyfour',

            # Go to theme folder
            'cd '.$name,
            
            # Remove and create README.md
            'rm -rf README.md',
            'echo "# '.ucfirst($name).'
            ## A Lamapress website
            " > README.md',

            # Remove and create style.css
            'rm -rf style.css',
            'echo "/*
            Theme Name: '.ucfirst($name).'
            Theme URI: http://'.$name.'.test/
            Author: Lama Lama
            Author URI: https://lamalama.nl/
            Version: 1.0
            */
            " > style.css',

            # Remove and create .gitignore
            'rm -rf .gitignore',
            'echo "# Ignore
            /node_modules
            .package-lock.json
            *.log
            .DS_Store
            " > .gitignore',

            # Removee .git folder from cloned theme
            'rm -rf .git',

            # Initialize a fresh git repository
            'git init -q',
            'git add .',
            'git commit -q -m "LamaPress init"',
            "git branch -M main",

            # Build
            'npm install',
            'npm run build',
        ];

        if ($directory != '.' && $input->getOption('force')) {
            if (PHP_OS_FAMILY == 'Windows') {
                array_unshift($commands, "(if exist \"$directory\" rd /s /q \"$directory\")");
            } else {
                array_unshift($commands, "rm -rf \"$directory\"");
            }
        }

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            $this->pushToGitHub($name, $directory, $input, $output);

            $output->writeln("".PHP_EOL);
            $output->writeln("<bg=blue;fg=white> INFO </> LamaPress ready on <options=bold>[http://{$name}.test]</>. Build something gezellebel.".PHP_EOL);
            $output->writeln("Username: $user".PHP_EOL);
            $output->writeln("Password: $password".PHP_EOL);
        }

        return $process->getExitCode();
    }

    /**
     * Return the local machine's default Git branch if set or default to `main`.
     */
    protected function defaultBranch(): string
    {
        $process = new Process(['git', 'config', '--global', 'init.defaultBranch']);

        $process->run();

        $output = trim($process->getOutput());

        return $process->isSuccessful() && $output ? $output : 'main';
    }

    /**
     * Create a GitHub repository and push the git log to it.
     */
    protected function pushToGitHub(string $name, string $directory, InputInterface $input, OutputInterface $output): void
    {
        $process = new Process(['gh', 'auth', 'status']);
        $process->run();

        if (! $process->isSuccessful()) {
            $output->writeln('  <bg=yellow;fg=black> WARN </> Make sure the "gh" CLI tool is installed and that you\'re authenticated to GitHub. Skipping...'.PHP_EOL);

            return;
        }

        $commands = [
            "gh repo create lamalamaNL/{$name} --source=. --push --private",
        ];

        $directory .= "/wp-content/themes/{$name}"; 

        $this->runCommands($commands, $input, $output, workingPath: $directory, env: ['GIT_TERMINAL_PROMPT' => 0]);
    }

    /**
     * Verify that the application does not already exist.
     */
    protected function verifyApplicationDoesntExist(string $directory): void
    {
        if ((is_dir($directory) || is_file($directory)) && $directory != getcwd()) {
            throw new RuntimeException('Application already exists!');
        }
    }

    /**
     * Run the given commands.
     */
    protected function runCommands($commands, InputInterface $input, OutputInterface $output, string $workingPath = null, array $env = []): Process
    {
        if (! $output->isDecorated()) {
            $commands = array_map(function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value.' --no-ansi';
            }, $commands);
        }

        if ($input->getOption('quiet')) {
            $commands = array_map(function ($value) {
                if (str_starts_with($value, 'chmod')) {
                    return $value;
                }

                if (str_starts_with($value, 'git')) {
                    return $value;
                }

                return $value.' --quiet';
            }, $commands);
        }

        $process = Process::fromShellCommandline(implode(' && ', $commands), $workingPath, $env, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write('    '.$line);
        });

        return $process;
    }
}
