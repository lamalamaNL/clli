<?php

namespace LamaLama\Clli\Console;

use LamaLama\Clli\Console\Services\CliConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class UpdateLocalConfigCommand extends BaseCommand
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
        $config = new CliConfig();
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
                required: 'The configuration key is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The key may only contain letters, numbers, dashes, underscores, and periods.'
                    : null
            );
        }

        $currentValue = $configData[$selectedKey] ?? '';
        
        $newValue = text(
            label: "Enter new value for '$selectedKey'",
            default: $currentValue,
            required: 'The configuration value is required.'
        );

        $config->set($selectedKey, $newValue);

        $output->writeln("<info>Successfully updated '$selectedKey' in configuration.</info>");

        return Command::SUCCESS;
    }
} 
