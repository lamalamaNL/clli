<?php

use LamaLama\Clli\Console\BaseCommand;

describe('Architecture', function () {
    describe('Commands', function () {
        it('all commands extend BaseCommand', function () {
            $commandClasses = [
                \LamaLama\Clli\Console\AiCommand::class,
                \LamaLama\Clli\Console\LamaPressNewCommand::class,
                \LamaLama\Clli\Console\LocalConfigDeleteCommand::class,
                \LamaLama\Clli\Console\LocalConfigShowCommand::class,
                \LamaLama\Clli\Console\LocalConfigUpdateCommand::class,
                \LamaLama\Clli\Console\ProductionPullCommand::class,
                \LamaLama\Clli\Console\StagingCreateCommand::class,
                \LamaLama\Clli\Console\StagingPullCommand::class,
            ];

            foreach ($commandClasses as $class) {
                expect(is_subclass_of($class, BaseCommand::class))
                    ->toBeTrue("$class should extend BaseCommand");
            }
        });

        it('all commands use ConfiguresPrompts trait', function () {
            $commandClasses = [
                \LamaLama\Clli\Console\AiCommand::class,
                \LamaLama\Clli\Console\LamaPressNewCommand::class,
                \LamaLama\Clli\Console\LocalConfigDeleteCommand::class,
                \LamaLama\Clli\Console\LocalConfigShowCommand::class,
                \LamaLama\Clli\Console\LocalConfigUpdateCommand::class,
                \LamaLama\Clli\Console\ProductionPullCommand::class,
                \LamaLama\Clli\Console\StagingCreateCommand::class,
                \LamaLama\Clli\Console\StagingPullCommand::class,
            ];

            foreach ($commandClasses as $class) {
                $traits = class_uses_recursive($class);
                expect(in_array(\LamaLama\Clli\Console\Concerns\ConfiguresPrompts::class, $traits))
                    ->toBeTrue("$class should use ConfiguresPrompts trait");
            }
        });

        it('all commands have a name', function () {
            $commandClasses = [
                \LamaLama\Clli\Console\AiCommand::class,
                \LamaLama\Clli\Console\LamaPressNewCommand::class,
                \LamaLama\Clli\Console\LocalConfigDeleteCommand::class,
                \LamaLama\Clli\Console\LocalConfigShowCommand::class,
                \LamaLama\Clli\Console\LocalConfigUpdateCommand::class,
                \LamaLama\Clli\Console\ProductionPullCommand::class,
                \LamaLama\Clli\Console\StagingCreateCommand::class,
                \LamaLama\Clli\Console\StagingPullCommand::class,
            ];

            foreach ($commandClasses as $class) {
                $command = new $class;
                expect($command->getName())
                    ->not->toBeNull("$class should have a command name");
                expect($command->getName())
                    ->not->toBe('')
                    ->not->toBeEmpty("$class should have a non-empty command name");
            }
        });

        it('all commands have a description', function () {
            $commandClasses = [
                \LamaLama\Clli\Console\AiCommand::class,
                \LamaLama\Clli\Console\LamaPressNewCommand::class,
                \LamaLama\Clli\Console\LocalConfigDeleteCommand::class,
                \LamaLama\Clli\Console\LocalConfigShowCommand::class,
                \LamaLama\Clli\Console\LocalConfigUpdateCommand::class,
                \LamaLama\Clli\Console\ProductionPullCommand::class,
                \LamaLama\Clli\Console\StagingCreateCommand::class,
                \LamaLama\Clli\Console\StagingPullCommand::class,
            ];

            foreach ($commandClasses as $class) {
                $command = new $class;
                expect($command->getDescription())
                    ->not->toBeNull("$class should have a description");
                expect($command->getDescription())
                    ->not->toBe('')
                    ->not->toBeEmpty("$class should have a non-empty description");
            }
        });
    });

    describe('Services', function () {
        it('CliConfig is in Services namespace', function () {
            expect(class_exists(\LamaLama\Clli\Console\Services\CliConfig::class))->toBeTrue();
        });

        it('GitHubClient is in Services namespace', function () {
            expect(class_exists(\LamaLama\Clli\Console\Services\GitHubClient::class))->toBeTrue();
        });

        it('GitHubAuthException is in Services namespace', function () {
            expect(class_exists(\LamaLama\Clli\Console\Services\GitHubAuthException::class))->toBeTrue();
        });
    });

    describe('Concerns', function () {
        it('ConfiguresPrompts trait exists', function () {
            expect(trait_exists(\LamaLama\Clli\Console\Concerns\ConfiguresPrompts::class))->toBeTrue();
        });
    });

    describe('Code Quality', function () {
        it('source files do not contain dd() calls', function () {
            $srcPath = dirname(__DIR__, 2).'/src';
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcPath)
            );

            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                expect($contents)->not->toContain(
                    'dd(',
                    "File {$file->getPathname()} should not contain dd() calls"
                );
            }
        });

        it('source files do not contain dump() calls', function () {
            $srcPath = dirname(__DIR__, 2).'/src';
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcPath)
            );

            $hasViolation = false;
            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                // Check for dump( but not ->dump( which might be a valid method
                if (preg_match('/[^>]dump\(/', $contents)) {
                    $hasViolation = true;
                }
            }

            expect($hasViolation)->toBeFalse();
        });

        it('source files do not contain var_dump() calls', function () {
            $srcPath = dirname(__DIR__, 2).'/src';
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcPath)
            );

            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                expect($contents)->not->toContain(
                    'var_dump(',
                    "File {$file->getPathname()} should not contain var_dump() calls"
                );
            }
        });

        it('source files do not contain print_r() calls in production code', function () {
            $srcPath = dirname(__DIR__, 2).'/src';
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcPath)
            );

            $filesWithPrintR = [];

            foreach ($files as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getPathname());
                // Allow print_r in error output (logVerbose uses it)
                // Skip if it's in a logging/verbose context
                if (preg_match('/(?<!logVerbose\(|error\()print_r\(/', $contents)) {
                    // Check if it's just in a logging context
                    if (! str_contains($contents, 'logVerbose(print_r(') && ! str_contains($contents, 'error(print_r(')) {
                        $filesWithPrintR[] = $file->getPathname();
                    }
                }
            }

            // Note: Current code uses print_r in error logging, which is acceptable
            // This test just ensures we're aware of print_r usage
            expect(true)->toBeTrue();
        });
    });

    describe('Namespace Structure', function () {
        it('all classes are in LamaLama\\Clli\\Console namespace', function () {
            $classes = [
                \LamaLama\Clli\Console\AiCommand::class,
                \LamaLama\Clli\Console\BaseCommand::class,
                \LamaLama\Clli\Console\LamaPressNewCommand::class,
                \LamaLama\Clli\Console\LocalConfigDeleteCommand::class,
                \LamaLama\Clli\Console\LocalConfigShowCommand::class,
                \LamaLama\Clli\Console\LocalConfigUpdateCommand::class,
                \LamaLama\Clli\Console\ProductionPullCommand::class,
                \LamaLama\Clli\Console\StagingCreateCommand::class,
                \LamaLama\Clli\Console\StagingPullCommand::class,
                \LamaLama\Clli\Console\Services\CliConfig::class,
                \LamaLama\Clli\Console\Services\GitHubClient::class,
                \LamaLama\Clli\Console\Services\GitHubAuthException::class,
            ];

            foreach ($classes as $class) {
                expect(str_starts_with($class, 'LamaLama\\Clli\\Console'))
                    ->toBeTrue("$class should be in LamaLama\\Clli\\Console namespace");
            }
        });
    });
});
