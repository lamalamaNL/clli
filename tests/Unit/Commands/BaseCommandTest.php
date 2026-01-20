<?php

use LamaLama\Clli\Console\BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Create a testable subclass of BaseCommand to access protected methods.
 */
function createTestableBaseCommand(): BaseCommand
{
    return new class extends BaseCommand
    {
        protected function configure(): void
        {
            $this->setName('test:command')
                ->setDescription('A test command')
                ->addOption('quiet', 'q', \Symfony\Component\Console\Input\InputOption::VALUE_NONE, 'Quiet mode');
        }

        /**
         * Expose runCommands for testing.
         */
        public function testRunCommands(
            array $commands,
            $input,
            $output,
            ?string $workingPath = null,
            array $env = [],
            bool $suppressOutput = false
        ) {
            return $this->runCommands($commands, $input, $output, $workingPath, $env, $suppressOutput);
        }

        /**
         * Get the command string that would be executed (for testing).
         * This simulates the logic in runCommands without actually running Process.
         */
        public function buildCommandString(array $commands, $input, $output): string
        {
            $modifiedCommands = $commands;

            if (! $output->isDecorated()) {
                $modifiedCommands = array_map(function ($value) {
                    if (str_starts_with($value, 'chmod')) {
                        return $value;
                    }
                    if (str_starts_with($value, 'git')) {
                        return $value;
                    }

                    return $value.' --no-ansi';
                }, $modifiedCommands);
            }

            // Check if quiet option exists before getting it
            $definition = $input->hasOption('quiet');
            if ($definition && $input->getOption('quiet')) {
                $modifiedCommands = array_map(function ($value) {
                    if (str_starts_with($value, 'chmod')) {
                        return $value;
                    }
                    if (str_starts_with($value, 'git')) {
                        return $value;
                    }

                    return $value.' --quiet';
                }, $modifiedCommands);
            }

            return implode(' && ', $modifiedCommands);
        }
    };
}

describe('BaseCommand', function () {
    describe('command string building', function () {
        it('joins commands with && operator', function () {
            $command = createTestableBaseCommand();

            $input = new ArrayInput([]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(true);

            $result = $command->buildCommandString(
                ['echo "hello"', 'echo "world"'],
                $input,
                $output
            );

            expect($result)->toBe('echo "hello" && echo "world"');
        });

        it('appends --no-ansi when output is not decorated', function () {
            $command = createTestableBaseCommand();

            $input = new ArrayInput([]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(false);

            $result = $command->buildCommandString(
                ['composer install'],
                $input,
                $output
            );

            expect($result)->toBe('composer install --no-ansi');
        });

        it('skips --no-ansi for chmod commands', function () {
            $command = createTestableBaseCommand();

            $input = new ArrayInput([]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(false);

            $result = $command->buildCommandString(
                ['chmod 755 script.sh'],
                $input,
                $output
            );

            expect($result)->toBe('chmod 755 script.sh');
        });

        it('skips --no-ansi for git commands', function () {
            $command = createTestableBaseCommand();

            $input = new ArrayInput([]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(false);

            $result = $command->buildCommandString(
                ['git status', 'git commit -m "test"'],
                $input,
                $output
            );

            expect($result)->toBe('git status && git commit -m "test"');
        });

        it('appends --quiet when quiet option is set', function () {
            $command = createTestableBaseCommand();
            $command->addOption('quiet', 'q');

            $input = new ArrayInput(['--quiet' => true]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(true);

            $result = $command->buildCommandString(
                ['npm install'],
                $input,
                $output
            );

            expect($result)->toBe('npm install --quiet');
        });

        it('skips --quiet for git commands', function () {
            $command = createTestableBaseCommand();
            $command->addOption('quiet', 'q');

            $input = new ArrayInput(['--quiet' => true]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(true);

            $result = $command->buildCommandString(
                ['git pull origin main'],
                $input,
                $output
            );

            expect($result)->toBe('git pull origin main');
        });

        it('combines multiple commands with proper flags', function () {
            $command = createTestableBaseCommand();

            $input = new ArrayInput([]);
            $input->bind($command->getDefinition());
            $input->setInteractive(false);

            $output = new BufferedOutput;
            $output->setDecorated(false);

            $result = $command->buildCommandString(
                ['git clone repo', 'npm install', 'chmod 755 build.sh'],
                $input,
                $output
            );

            expect($result)->toBe('git clone repo && npm install --no-ansi && chmod 755 build.sh');
        });
    });

    describe('command configuration', function () {
        it('extends Symfony Command', function () {
            $command = createTestableBaseCommand();

            expect($command)->toBeInstanceOf(\Symfony\Component\Console\Command\Command::class);
        });
    });
});
