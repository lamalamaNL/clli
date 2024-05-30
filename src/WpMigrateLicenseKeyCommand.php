<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\text;

class WpMigrateLicenseKeyCommand extends BaseCommand
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
            ->setName('wp-migrate:license-key')
            ->addArgument('license_key', InputArgument::REQUIRED)
            ->setDescription('Add a Migrate DB license key to your system');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        $output->write('<fg=white>
 ░▒▓██████▓▒░░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
░▒▓█▓▒░░▒▓█▓▒░▒▓█▓▒░      ░▒▓█▓▒░      ░▒▓█▓▒░ 
 ░▒▓██████▓▒░░▒▓████████▓▒░▒▓████████▓▒░▒▓█▓▒░'.PHP_EOL.PHP_EOL);

        if (! $input->getArgument('license_key')) {
            $input->setArgument('license_key', text(
                label: 'What is your WP Migrate license key?',
                placeholder: 'E.g. a788f99e-ab59-482e-8bfa-0c73b3ec1fbe',
                required: 'The license key is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The license may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $licenseKey = $input->getArgument('license_key');
        
        $commands = [
            'type jq >/dev/null 2>&1 || { echo >&2 "jq is not installed. Installing..."; brew install jq; }',
            'jq \'. + {"wp_migrate_license_key": "'.$licenseKey.'"}\' ~/.clli/config.json > ~/.clli/temp_config.json && mv ~/.clli/temp_config.json ~/.clli/config.json',
            'jq --arg updated_at "$(date +\'%Y-%m-%d %H:%M:%S\')" \'.updated_at = $updated_at\' ~/.clli/config.json > ~/.clli/temp_config.json && mv ~/.clli/temp_config.json ~/.clli/config.json',
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            // $output->writeln('Done'.PHP_EOL);
        }

        return $process->getExitCode();
    }
}
