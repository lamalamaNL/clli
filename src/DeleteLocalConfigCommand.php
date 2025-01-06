<?php

namespace LamaLama\Clli\Console;

use LamaLama\Clli\Console\Services\CliConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\select;

class DeleteLocalConfigCommand extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('config:delete')
            ->setDescription('Delete a value from the CLLI config file');
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

        $selectedKey = select(
            label: 'Which configuration key would you like to delete?',
            options: $options
        );

        $config->delete($selectedKey);

        $output->writeln("<info>Successfully deleted '$selectedKey' from configuration.</info>");

        return Command::SUCCESS;
    }
}
