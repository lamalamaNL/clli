<?php

namespace LamaLama\Clli\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateLamapressSectionPreviews extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('lamapress:generate-preview')
            ->setDescription('Generate section previews for your lamapress project');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        echo 'test';

        return Command::SUCCESS;
    }
}
