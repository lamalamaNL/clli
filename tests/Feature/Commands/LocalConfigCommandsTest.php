<?php

use LamaLama\Clli\Console\LocalConfigDeleteCommand;
use LamaLama\Clli\Console\LocalConfigShowCommand;
use LamaLama\Clli\Console\LocalConfigUpdateCommand;
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

describe('LocalConfigShowCommand', function () {
    it('is configured with correct name', function () {
        $command = new LocalConfigShowCommand;

        expect($command->getName())->toBe('config:show');
    });

    it('is configured with correct description', function () {
        $command = new LocalConfigShowCommand;

        expect($command->getDescription())->toBe('Show the local CLLI config file');
    });

    it('can be added to application', function () {
        $app = new Application;
        $app->add(new LocalConfigShowCommand);

        expect($app->has('config:show'))->toBeTrue();
    });

    it('returns failure when config is empty', function () {
        // Ensure config directory exists but file has empty object
        mkdir($this->tempDir.'/.clli', 0777, true);
        file_put_contents($this->tempDir.'/.clli/config.json', '{}');

        $app = new Application;
        $app->add(new LocalConfigShowCommand);

        $command = $app->find('config:show');
        $tester = new CommandTester($command);

        // Run non-interactively
        $tester->execute([], ['interactive' => false]);

        // Empty config {} returns failure per the command logic
        expect($tester->getStatusCode())->toBe(1);
        expect($tester->getDisplay())->toContain('No configuration found');
    });

    it('displays config as JSON when config exists', function () {
        // Create config with some data
        $config = new CliConfig;
        $config->set('test_key', 'test_value');

        $app = new Application;
        $app->add(new LocalConfigShowCommand);

        $command = $app->find('config:show');
        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        $output = $tester->getDisplay();

        expect($output)->toContain('test_key');
        expect($output)->toContain('test_value');
        expect($tester->getStatusCode())->toBe(0);
    });
});

describe('LocalConfigUpdateCommand', function () {
    it('is configured with correct name', function () {
        $command = new LocalConfigUpdateCommand;

        expect($command->getName())->toBe('config:update');
    });

    it('is configured with correct description', function () {
        $command = new LocalConfigUpdateCommand;

        expect($command->getDescription())->toBe('Update a value in the CLLI config file');
    });

    it('can be added to application', function () {
        $app = new Application;
        $app->add(new LocalConfigUpdateCommand);

        expect($app->has('config:update'))->toBeTrue();
    });
});

describe('LocalConfigDeleteCommand', function () {
    it('is configured with correct name', function () {
        $command = new LocalConfigDeleteCommand;

        expect($command->getName())->toBe('config:delete');
    });

    it('is configured with correct description', function () {
        $command = new LocalConfigDeleteCommand;

        expect($command->getDescription())->toBe('Delete a value from the CLLI config file');
    });

    it('can be added to application', function () {
        $app = new Application;
        $app->add(new LocalConfigDeleteCommand);

        expect($app->has('config:delete'))->toBeTrue();
    });
});

describe('All config commands', function () {
    it('can all be registered to the same application', function () {
        $app = new Application;
        $app->add(new LocalConfigShowCommand);
        $app->add(new LocalConfigUpdateCommand);
        $app->add(new LocalConfigDeleteCommand);

        expect($app->has('config:show'))->toBeTrue();
        expect($app->has('config:update'))->toBeTrue();
        expect($app->has('config:delete'))->toBeTrue();
    });

    it('all extend BaseCommand', function () {
        $showCommand = new LocalConfigShowCommand;
        $updateCommand = new LocalConfigUpdateCommand;
        $deleteCommand = new LocalConfigDeleteCommand;

        expect($showCommand)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        expect($updateCommand)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        expect($deleteCommand)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
    });
});
