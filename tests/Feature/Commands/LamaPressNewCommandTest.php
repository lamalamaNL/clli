<?php

use LamaLama\Clli\Console\LamaPressNewCommand;
use LamaLama\Clli\Console\Services\CliConfig;
use Symfony\Component\Console\Application;

beforeEach(function () {
    $this->tempDir = createTempDirectory();
    $this->originalHome = getenv('HOME');
    putenv('HOME='.$this->tempDir);

    // Set up required config keys
    $config = new CliConfig;
    $config->set('wp_migrate_license_key', 'test-license-key');
});

afterEach(function () {
    putenv('HOME='.$this->originalHome);
    cleanupTempDirectory($this->tempDir);
});

describe('LamaPressNewCommand', function () {
    describe('command configuration', function () {
        it('has correct command name', function () {
            $command = new LamaPressNewCommand;

            expect($command->getName())->toBe('lamapress:new');
        });

        it('has correct description', function () {
            $command = new LamaPressNewCommand;

            expect($command->getDescription())->toBe('Create a new LamaPress application');
        });

        it('has optional name argument', function () {
            $command = new LamaPressNewCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('name'))->toBeTrue();
            expect($definition->getArgument('name')->isRequired())->toBeFalse();
        });

        it('can be registered to application', function () {
            $app = new Application;
            $app->add(new LamaPressNewCommand);

            expect($app->has('lamapress:new'))->toBeTrue();
        });

        it('extends BaseCommand', function () {
            $command = new LamaPressNewCommand;

            expect($command)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        });
    });

    describe('verifyDirectory()', function () {
        it('throws exception when directory already exists', function () {
            $command = new LamaPressNewCommand;

            // Create a directory that "already exists"
            $existingDir = $this->tempDir.'/existing-project';
            mkdir($existingDir, 0777, true);

            setPrivateProperty($command, 'directory', $existingDir);

            expect(fn () => callPrivateMethod($command, 'verifyDirectory'))
                ->toThrow(RuntimeException::class, 'Application already exists!');
        });

        it('does not throw when directory does not exist', function () {
            $command = new LamaPressNewCommand;

            $nonExistentDir = $this->tempDir.'/new-project';
            setPrivateProperty($command, 'directory', $nonExistentDir);

            // Should not throw
            callPrivateMethod($command, 'verifyDirectory');

            expect(true)->toBeTrue(); // If we get here, no exception was thrown
        });

        it('does not throw when directory is current directory', function () {
            $command = new LamaPressNewCommand;

            // When directory equals getcwd(), it should not throw
            setPrivateProperty($command, 'directory', getcwd());

            // Should not throw even if directory exists
            callPrivateMethod($command, 'verifyDirectory');

            expect(true)->toBeTrue();
        });
    });

    describe('checkClliConfig()', function () {
        it('returns true when required keys are present', function () {
            $command = new LamaPressNewCommand;

            // Config already has wp_migrate_license_key from beforeEach
            $result = callPrivateMethod($command, 'checkClliConfig');

            expect($result)->toBeTrue();
        });
    });

    describe('constants', function () {
        it('has correct default admin user', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['DEFAULT_ADMIN_USER'])->toBe('lamalama');
        });

        it('has correct default admin email', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['DEFAULT_ADMIN_EMAIL'])->toBe('wordpress@lamalama.nl');
        });

        it('has correct GitHub organization', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['GITHUB_ORG'])->toBe('lamalamaNL');
        });

        it('has correct theme boilerplate repo', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['THEME_BOILERPLATE_REPO'])->toBe('https://github.com/lamalamaNL/lamapress.git');
        });

        it('has correct plugin download URL', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['PLUGIN_DOWNLOAD_URL'])->toBe('https://downloads.lamapress.nl');
        });
    });

    describe('plugins configuration', function () {
        it('has plugins to delete list', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['PLUGINS_TO_DELETE'])->toContain('akismet');
            expect($constants['PLUGINS_TO_DELETE'])->toContain('hello');
        });

        it('has premium plugins list', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['PREMIUM_PLUGINS'])->toContain('advanced-custom-fields-pro.zip');
            expect($constants['PREMIUM_PLUGINS'])->toContain('wp-migrate-db-pro.zip');
        });

        it('has active plugins list', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['ACTIVE_PLUGINS'])->toContain('wordpress-seo');
            expect($constants['ACTIVE_PLUGINS'])->toContain('classic-editor');
        });

        it('has inactive plugins list', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['INACTIVE_PLUGINS'])->toContain('wordfence');
            expect($constants['INACTIVE_PLUGINS'])->toContain('restricted-site-access');
        });

        it('has WPML plugins list', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['WPML_PLUGINS'])->toContain('sitepress-multilingual-cms.zip');
            expect($constants['WPML_PLUGINS'])->toContain('wpml-string-translation.zip');
        });
    });

    describe('admin users configuration', function () {
        it('has lamalama admin user', function () {
            $reflection = new ReflectionClass(LamaPressNewCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['ADMIN_USERS'])->toHaveKey('lamalama');
            expect($constants['ADMIN_USERS']['lamalama'])->toBe('wordpress@lamalama.nl');
        });
    });
});
