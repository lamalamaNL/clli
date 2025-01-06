<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowLocalConfigCommand extends BaseCommand
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
            ->setName('config:show')
            ->setDescription('Show the local CLLI config file');
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
            'type jq >/dev/null 2>&1 || { echo >&2 "jq is not installed. Installing..."; brew install jq; }',
            "cat ~/.clli/config.json | jq '.'",
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            //
        }

        return $process->getExitCode();
    }
}
