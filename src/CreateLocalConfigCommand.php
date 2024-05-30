<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateLocalConfigCommand extends BaseCommand
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
            ->setName('config:create')
            ->setDescription('Create a local CLLI config file');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $commands = [
            'mkdir -p ~/.clli',
            '[ ! -f ~/.clli/config.json ] && jq -n \
            --arg version "1.0.0" \
            --arg created_at "$(date +\'%Y-%m-%d %H:%M:%S\')" \
            \'{version: $version, created_at: $created_at, updated_at: $created_at}\' > ~/.clli/config.json',
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            $output->writeln('Config file created'.PHP_EOL);
        }

        return $process->getExitCode();
    }
}
