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
use function Laravel\Prompts\text;

class StagingPullCommand extends BaseCommand
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
            ->setName('staging:pull')
            ->addArgument('connection_info', InputArgument::REQUIRED)
            ->addOption('repository_url', 'r', InputArgument::OPTIONAL, 'Overwrite the git clone url for the repository (default: git@github.com:lamalamaNL/<name>.git)')
            ->setDescription('Pull a staging environment');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $this->configurePrompts($input, $output);

        intro('Lama Lama CLLI - Pull Staging Environment');

        // Pattern for validation: URL followed by connection key
        $pattern = '/^https:\/\/[a-zA-Z0-9\-]+\.lamalama\.dev\s+[a-zA-Z0-9+\-\/]+$/';

        $input->setArgument('connection_info', text(
            label: 'What is the WP Migrate DB connection info?',
            placeholder: 'E.g. https://projectx.lamalama.dev qQSr+EVrJ83uIkME/zQiCBb4V/nVaG1dzh5vmqEq',
            hint: 'Format: <url> <connection-key> (space-separated)',
            required: 'The WP Migrate DB connection info is required.',
            validate: fn ($value) => preg_match($pattern, $value) !== 0
                ? null
                : 'Invalid format. Expected: https://domain.lamalama.dev <connection-key>',
        ));
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connectionInfo = $input->getArgument('connection_info');

        $connectionInfoParts = explode(' ', $connectionInfo);
        $domain = $connectionInfoParts[0];

        $name = str_replace('https://', '', $connectionInfoParts[0]);
        $name = str_replace('www.', '', $name);
        $name = str_replace('.lamalama.dev', '', $name);
        $name = str_replace('.nl', '', $name);
        $name = str_replace('.com', '', $name);

        $repositoryUrl = $input->getOption('repository_url') ?? 'git@github.com:lamalamaNL/'.$name.'.git';

        $user = 'lamalama';
        $password = md5(time().uniqid());
        $email = 'wordpress@lamalama.nl';

        $dbName = str_replace('-', '_', strtolower($name));
        $dbName .= '_'.rand(10000, 99999);

        $commands = [
            'if [ -d "./'.$name.'" ]; then
                echo "Directory does already exist."
                exit 1
            fi',
            'mkdir -p '.$name,
            'cd '.$name,

            // Install WordPress
            'wp core download',
            'wp config create --dbname="'.$dbName.'" --dbuser="root" --dbpass="" --dbhost="127.0.0.1" --dbprefix=wp_',
            'wp db create',
            'wp core install --url="http://'.$name.'.test" --title="'.ucfirst($name).'" --admin_user="'.$user.'" --admin_password="'.$password.'" --admin_email="'.$email.'"',

            // Delete plugins
            'wp plugin delete akismet',
            'wp plugin delete hello',

            // Install plugins and activate
            'wp plugin install https://downloads.lamapress.nl/wp-migrate-db-pro.zip --activate',
            'wp_migrate_license_key=$(jq -r \'.wp_migrate_license_key\' ~/.clli/config.json)',
            'wp migrate setting update license $wp_migrate_license_key --user='.$email,
            'wp plugin update --all',

            // Clone Lamapress WP boilerplate
            'cd wp-content/themes',
            'git clone --depth=1  '.$repositoryUrl.' '.$name,
            'wp theme activate '.$name,

            // Delete default themes
            'wp theme delete twentytwentythree',
            'wp theme delete twentytwentyfour',
            'wp theme delete twentytwentyfive',

            // Go to theme folder
            'cd '.$name,

            // Migrate
            'wp migrate pull '.$connectionInfo.' \
                --find='.$domain.' \
                --replace=http://'.$name.'.test \
                --media=all \
                --plugin-files=all',

            // Build
            'npm install',
            'npm run build',
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            info('');
            info('Local staging site ready at [http://'.$name.'.test]');
            info('Admin panel at [http://'.$name.'.test/wp-admin]');
            outro('Staging environment pulled successfully!');
        }

        return $process->getExitCode();
    }
}
