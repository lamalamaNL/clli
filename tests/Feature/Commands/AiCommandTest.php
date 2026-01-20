<?php

use LamaLama\Clli\Console\AiCommand;
use LamaLama\Clli\Console\Services\CliConfig;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

beforeEach(function () {
    $this->tempDir = createTempDirectory();
    $this->originalHome = getenv('HOME');
    putenv('HOME='.$this->tempDir);
});

afterEach(function () {
    putenv('HOME='.$this->originalHome);
    cleanupTempDirectory($this->tempDir);
});

describe('AiCommand', function () {
    describe('command configuration', function () {
        it('has correct command name', function () {
            $command = new AiCommand;

            expect($command->getName())->toBe('ai:story');
        });

        it('has correct description', function () {
            $command = new AiCommand;

            expect($command->getDescription())->toBe('Ask Lama Lama a bedtime story');
        });

        it('can be registered to application', function () {
            $app = new Application;
            $app->add(new AiCommand);

            expect($app->has('ai:story'))->toBeTrue();
        });

        it('extends BaseCommand', function () {
            $command = new AiCommand;

            expect($command)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        });
    });

    describe('execute()', function () {
        it('returns failure when API key is not configured', function () {
            // Ensure config exists but has no API key
            $config = new CliConfig;
            // Don't set openai_api_key

            $app = new Application;
            $app->add(new AiCommand);

            $command = $app->find('ai:story');
            $tester = new CommandTester($command);

            $tester->execute([], ['interactive' => false]);

            // The command returns failure (exit code 1)
            expect($tester->getStatusCode())->toBe(1);
        });
    });

    describe('OpenAI integration', function () {
        // Note: These tests verify the command structure without calling the actual API.
        // The actual OpenAI call would need to be mocked at a deeper level using
        // dependency injection or by mocking the OpenAI client factory.

        it('uses CliConfig to get API key', function () {
            $config = new CliConfig;
            $config->set('openai_api_key', 'test-key-12345');

            $storedKey = $config->get('openai_api_key');

            expect($storedKey)->toBe('test-key-12345');
        });

        it('retrieves API key from correct config location', function () {
            $config = new CliConfig;
            $config->set('openai_api_key', 'sk-test-key');

            // Verify the key is stored in the expected location
            $configPath = $this->tempDir.'/.clli/config.json';
            $configData = json_decode(file_get_contents($configPath), true);

            expect($configData['openai_api_key'])->toBe('sk-test-key');
        });
    });
});
