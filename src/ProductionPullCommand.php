<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

class ProductionPullCommand extends BaseCommand
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

    protected bool $verbose = false;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('production:pull')
            ->addArgument('connection_info', InputArgument::REQUIRED)
            ->addArgument('name', InputArgument::REQUIRED)
            ->addOption('repository_url', 'r', InputArgument::OPTIONAL, 'Overwrite the git clone url for the repository (default: git@github.com:lamalamaNL/<name>.git)')
            ->setDescription('Pull a production environment');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        intro('Lama Lama CLLI - Pull Production Environment');

        // Pattern for validation: URL followed by connection key (accepts any domain)
        $pattern = '/^https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\s+[a-zA-Z0-9+\-\/]+$/';

        $connectionInfo = text(
            label: 'What is the WP Migrate DB connection info?',
            placeholder: 'E.g. https://www.clientsite.nl qQSr+EVrJ83uIkME/zQiCBb4V/nVaG1dzh5vmqEq',
            hint: 'Format: <url> <connection-key> (space-separated)',
            required: 'The WP Migrate DB connection info is required.',
            validate: fn ($value) => preg_match($pattern, $value) !== 0
                ? null
                : 'Invalid format. Expected: https://domain.com <connection-key>',
        );

        $input->setArgument('connection_info', $connectionInfo);

        // Derive suggested project name from domain
        $connectionInfoParts = explode(' ', $connectionInfo);
        $domain = $connectionInfoParts[0];
        $suggestedName = preg_replace('/^https?:\/\//', '', $domain);
        $suggestedName = preg_replace('/^www\./', '', $suggestedName);
        $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName);
        $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName); // Handle .co.uk etc.

        $input->setArgument('name', text(
            label: 'What should the local project folder be named?',
            placeholder: 'E.g. clientsite',
            default: $suggestedName,
            hint: 'This will create a local folder and http://<name>.test domain',
            required: 'The project name is required.',
        ));
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->output = $output;
        $this->verbose = $output->isVerbose();

        $connectionInfo = $input->getArgument('connection_info');
        $name = $input->getArgument('name');

        $connectionInfoParts = explode(' ', $connectionInfo);
        $domain = $connectionInfoParts[0];

        $repositoryUrl = $input->getOption('repository_url') ?? 'git@github.com:lamalamaNL/'.$name.'.git';

        $user = 'lamalama';
        $password = md5(time().uniqid());
        $email = 'wordpress@lamalama.nl';

        $dbName = str_replace('-', '_', strtolower($name));
        $dbName .= '_'.rand(10000, 99999);

        $steps = [
            [
                'message' => 'Creating project directory',
                'commands' => [
                    'if [ -d "./'.$name.'" ]; then
                        echo "Directory does already exist."
                        exit 1
                    fi',
                    'mkdir -p '.$name,
                ],
            ],
            [
                'message' => 'Installing WordPress',
                'commands' => [
                    'cd '.$name,
                    'wp core download',
                    'wp config create --dbname="'.$dbName.'" --dbuser="root" --dbpass="" --dbhost="127.0.0.1" --dbprefix=wp_',
                    'wp db create',
                    'wp core install --url="http://'.$name.'.test" --title="'.ucfirst($name).'" --admin_user="'.$user.'" --admin_password="'.$password.'" --admin_email="'.$email.'"',
                ],
            ],
            [
                'message' => 'Configuring plugins',
                'commands' => [
                    'cd '.$name,
                    'wp plugin delete akismet',
                    'wp plugin delete hello',
                    'wp plugin install https://downloads.lamapress.nl/wp-migrate-db-pro.zip --activate',
                    'wp_migrate_license_key=$(jq -r \'.wp_migrate_license_key\' ~/.clli/config.json)',
                    'wp migrate setting update license $wp_migrate_license_key --user='.$email,
                    'wp plugin update --all',
                ],
            ],
            [
                'message' => 'Cloning theme repository',
                'commands' => [
                    'cd '.$name.'/wp-content/themes',
                    'git clone --depth=1  '.$repositoryUrl.' '.$name,
                    'wp theme activate '.$name,
                    'wp theme delete twentytwentythree',
                    'wp theme delete twentytwentyfour',
                    'wp theme delete twentytwentyfive',
                ],
            ],
            [
                'message' => 'Migrating database and media',
                'commands' => [
                    'cd '.$name.'/wp-content/themes/'.$name,
                    'wp migrate pull '.$connectionInfo.' \
                        --find='.$domain.' \
                        --replace=http://'.$name.'.test \
                        --media=all \
                        --plugin-files=all',
                ],
            ],
            [
                'message' => 'Building assets',
                'commands' => [
                    'cd '.$name.'/wp-content/themes/'.$name,
                    'npm install',
                    // Detect build script: use 'build' if available, otherwise 'prod'
                    'if npm run 2>/dev/null | grep -q "build"; then npm run build; elif npm run 2>/dev/null | grep -q "prod"; then npm run prod; else echo "No build or prod script found"; fi',
                ],
            ],
        ];

        $totalSteps = count($steps);
        $initialStartTime = microtime(true);

        foreach ($steps as $index => $step) {
            $startTime = microtime(true);
            $message = 'Step '.($index + 1).'/'.$totalSteps.': '.$step['message'];

            if ($this->verbose) {
                $output->writeln("<info>{$message}...</info>");
                $process = $this->runCommands($step['commands'], $input, $output, null, [], false);
            } else {
                $process = spin(
                    message: $message,
                    callback: fn () => $this->runCommands($step['commands'], $input, $output, null, [], true),
                );
            }

            if (! $process->isSuccessful()) {
                return $process->getExitCode();
            }

            info("✅ $message (".round(microtime(true) - $startTime, 2).'s)');
        }

        info('');
        info('🎉 All done in '.round(microtime(true) - $initialStartTime, 2).'s');
        info('');
        info('Local production site ready at [http://'.$name.'.test]');
        info('Admin panel at [http://'.$name.'.test/wp-admin]');
        outro('Production environment pulled successfully!');

        return Command::SUCCESS;
    }
}
