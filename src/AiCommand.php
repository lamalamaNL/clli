<?php

namespace LamaLama\Clli\Console;

use LamaLama\Clli\Console\Services\CliConfig;
use OpenAI;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class AiCommand extends BaseCommand
{
    use Concerns\ConfiguresPrompts;

    /**
     * Configure the command options.
     */
    protected function configure(): void
    {
        $this
            ->setName('ai:story')
            ->setDescription('Ask Lama Lama a bedtime story');
    }

    /**
     * Interact with the user before validating the input.
     */
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        intro('Lama Lama CLLI - AI Story');
    }

    /**
     * Execute the command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = new CliConfig;
        $yourApiKey = $config->get('openai_api_key');

        if (! $yourApiKey) {
            error('API key not found in CLI config. Please set it using: clli config:update');

            return Command::FAILURE;
        }

        $client = OpenAI::client($yourApiKey);

        $response = $client->responses()->create([
            'model' => 'gpt-5',
            'input' => 'Write a short bedtime story about a llama.',
        ]);

        $output->writeln($response->outputText);

        outro('Story generated successfully!');

        return Command::SUCCESS;
    }
}
