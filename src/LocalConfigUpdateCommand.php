<?php

namespace LamaLama\Clli\Console;

use LamaLama\Clli\Console\Services\CliConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class LocalConfigUpdateCommand extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('config:update')
            ->setDescription('Update a value in the CLLI config file');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        intro('Lama Lama CLLI - Update Configuration');
    }

    /**
     * Get hint text for a configuration key.
     */
    private function getConfigHint(string $key): string
    {
        return match ($key) {
            'forge_token' => 'Get this from https://forge.laravel.com/user/profile#/api',
            'forge_server_id' => 'Select a server from your Forge account',
            'forge_organization_id' => 'Found in the Forge dashboard URL when viewing an organization',
            'cloudflare_token' => 'Generate via \'Create Token\' at https://dash.cloudflare.com/profile/api-tokens',
            'cloudflare_zone_id' => 'Found in the Overview tab of your domain on https://dash.cloudflare.com',
            'wp_migrate_license_key' => 'Available in your WP Migrate account at https://deliciousbrains.com/my-account/licenses',
            'openai_api_key' => 'Get this from https://platform.openai.com/api-keys',
            'public_key_filename' => 'The filename of your SSH key in ~/.ssh/ (without .pub extension)',
            default => '',
        };
    }

    /**
     * Check if a key typically has a long value that would benefit from textarea.
     */
    private function isLongValueKey(string $key): bool
    {
        // Keys that might have longer values
        return in_array($key, [
            'forge_token',
            'cloudflare_token',
            'wp_migrate_license_key',
            'openai_api_key',
        ]);
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new CliConfig;
        $configData = $config->read();

        if (empty($configData)) {
            $output->writeln('<error>No configuration found.</error>');

            return Command::FAILURE;
        }

        // Remove created_at and updated_at from options since they're managed automatically
        unset($configData['created_at'], $configData['updated_at']);

        // Create options array for select prompt
        $options = array_keys($configData);

        if (empty($options)) {
            $output->writeln('<error>No configurable keys found.</error>');

            return Command::FAILURE;
        }

        // Add option to create new key
        $options[] = '+ Create new key';

        $selectedKey = select(
            label: 'Which configuration key would you like to update?',
            options: $options
        );

        if ($selectedKey === '+ Create new key') {
            $selectedKey = text(
                label: 'Enter new configuration key',
                placeholder: 'E.g. my_custom_key',
                hint: 'Use lowercase letters, numbers, dashes, underscores, and periods only',
                required: 'The configuration key is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The key may only contain letters, numbers, dashes, underscores, and periods.'
                    : null
            );
        }

        $currentValue = $configData[$selectedKey] ?? '';
        $hint = $this->getConfigHint($selectedKey);

        if ($this->isLongValueKey($selectedKey)) {
            $newValue = textarea(
                label: "Enter new value for '$selectedKey'",
                default: $currentValue,
                hint: $hint ?: 'Press Ctrl+D (or Cmd+D on Mac) when finished',
                required: 'The configuration value is required.'
            );
        } else {
            $newValue = text(
                label: "Enter new value for '$selectedKey'",
                default: $currentValue,
                hint: $hint,
                required: 'The configuration value is required.'
            );
        }

        $config->set($selectedKey, $newValue);

        outro("Successfully updated '$selectedKey' in configuration.");

        return Command::SUCCESS;
    }
}
