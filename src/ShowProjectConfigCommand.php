<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ShowProjectConfigCommand extends BaseCommand
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
            ->setName('lamapress:config')
            ->setDescription('Create a LamaPress CLLI config file');
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
            'if [ -f style.css ] && [ -d components ]; then
                echo ""
            else
                echo "You are not in a Lamapress theme folder."
                exit 1
            fi',
            'if [ ! -f ./.clli.json ]; then
                jq -n \
                --arg version "1.0.0" \
                --arg created_at "$(date +\'%Y-%m-%d %H:%M:%S\')" \
                \'{version: $version, created_at: $created_at}\' > ./.clli.json
            fi',
            "cat ./.clli.json | jq '.'",
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            // $output->writeln('Config file created.'.PHP_EOL);
        }

        return $process->getExitCode();
    }
}
