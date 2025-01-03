<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\text;

class FigmaAccessToken extends BaseCommand
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
            ->setName('figma:access-token')
            ->addArgument('access_token', InputArgument::REQUIRED)
            ->setDescription('Add a Figma personal access token to your system');
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

        if (! $input->getArgument('access_token')) {
            $input->setArgument('access_token', text(
                label: 'What is your Figma personal access token?',
                placeholder: 'E.g. figd_1kjLJsf8KJC__uadfJASh89asdfnAadfnkS94lks',
                required: 'The personal access token is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The personal access token may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $accessToken = $input->getArgument('access_token');

        $commands = [
            'type jq >/dev/null 2>&1 || { echo >&2 "jq is not installed. Installing..."; brew install jq; }',
            'jq \'. + {"figma_personal_access_token": "'.$accessToken.'"}\' ~/.clli/config.json > ~/.clli/temp_config.json && mv ~/.clli/temp_config.json ~/.clli/config.json',
            'jq --arg updated_at "$(date +\'%Y-%m-%d %H:%M:%S\')" \'.updated_at = $updated_at\' ~/.clli/config.json > ~/.clli/temp_config.json && mv ~/.clli/temp_config.json ~/.clli/config.json',
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            // $output->writeln('Done'.PHP_EOL);
        }

        return $process->getExitCode();
    }
}
