<?php

namespace LamaLama\Clli\Console;

use Illuminate\Support\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\text;

class FigmaConnect extends BaseCommand
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
            ->setName('figma:connect')
            ->addArgument('key', InputArgument::REQUIRED)
            ->setDescription('Connect a Figma file to your project');
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

        if (! $input->getArgument('key')) {
            $input->setArgument('key', text(
                label: 'What is the key of your Figma file?',
                placeholder: 'E.g. 6sftwMKT80KxNnVjS9cZcX',
                required: 'The file key is required.',
                validate: fn ($value) => preg_match('/[^\pL\pN\-_.]/', $value) !== 0
                    ? 'The key may only contain letters, numbers, dashes, underscores, and periods.'
                    : null,
            ));
        }
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = $input->getArgument('key');
        
        $commands = [
            'type jq >/dev/null 2>&1 || { echo >&2 "jq is not installed. Installing..."; brew install jq; }',
            'jq \'. + {"figma_file_key": "'.$key.'"}\' ./.clli.json > ./.temp_clli.json && mv ./.temp_clli.json ./.clli.json'
        ];

        if (($process = $this->runCommands($commands, $input, $output))->isSuccessful()) {
            // $output->writeln('Done'.PHP_EOL);
        }

        return $process->getExitCode();
    }
}
