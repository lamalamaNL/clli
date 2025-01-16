<?php

namespace LamaLama\Clli\Console;

use LamaLama\Clli\Console\Services\CliConfig;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LocalConfigShowCommand extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('config:show')
            ->setDescription('Show the local CLLI config file');
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

        // Format and output the JSON with indentation
        $output->writeln(json_encode($configData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}
