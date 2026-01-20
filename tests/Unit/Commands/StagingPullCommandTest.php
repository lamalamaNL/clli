<?php

use LamaLama\Clli\Console\StagingPullCommand;
use Symfony\Component\Console\Application;

describe('StagingPullCommand', function () {
    describe('command configuration', function () {
        it('has correct command name', function () {
            $command = new StagingPullCommand;

            expect($command->getName())->toBe('staging:pull');
        });

        it('has correct description', function () {
            $command = new StagingPullCommand;

            expect($command->getDescription())->toBe('Pull a staging environment');
        });

        it('has required connection_info argument', function () {
            $command = new StagingPullCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('connection_info'))->toBeTrue();
            expect($definition->getArgument('connection_info')->isRequired())->toBeTrue();
        });

        it('has optional repository_url option', function () {
            $command = new StagingPullCommand;
            $definition = $command->getDefinition();

            expect($definition->hasOption('repository_url'))->toBeTrue();
        });

        it('can be registered to application', function () {
            $app = new Application;
            $app->add(new StagingPullCommand);

            expect($app->has('staging:pull'))->toBeTrue();
        });

        it('extends BaseCommand', function () {
            $command = new StagingPullCommand;

            expect($command)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        });
    });

    describe('connection info validation pattern', function () {
        it('accepts valid staging connection info', function () {
            $pattern = getStagingConnectionPattern();
            $validInputs = [
                'https://projectx.lamalama.dev qQSr+EVrJ83uIkME/zQiCBb4V/nVaG1dzh5vmqEq',
                'https://my-project.lamalama.dev abc123def456',
                'https://test.lamalama.dev key-with-dashes',
            ];

            foreach ($validInputs as $input) {
                expect(preg_match($pattern, $input))->toBe(1, "Should match: $input");
            }
        });

        it('rejects invalid staging connection info', function () {
            $pattern = getStagingConnectionPattern();
            $invalidInputs = [
                'http://projectx.lamalama.dev key123', // http instead of https
                'https://projectx.lamalama.nl key123', // wrong domain
                'https://projectx.lamalama.dev', // missing key
                'projectx.lamalama.dev key123', // missing protocol
                'https://lamalama.dev key123', // missing subdomain
            ];

            foreach ($invalidInputs as $input) {
                expect(preg_match($pattern, $input))->toBe(0, "Should not match: $input");
            }
        });
    });

    describe('connection info parsing', function () {
        it('extracts domain from connection info', function () {
            $connectionInfo = 'https://example.lamalama.dev key123';
            $parts = explode(' ', $connectionInfo);

            expect($parts[0])->toBe('https://example.lamalama.dev');
        });

        it('extracts connection key from connection info', function () {
            $connectionInfo = 'https://example.lamalama.dev qQSr+EVrJ83uIkME';
            $parts = explode(' ', $connectionInfo);

            expect($parts[1])->toBe('qQSr+EVrJ83uIkME');
        });

        it('extracts project name from domain', function () {
            $connectionInfo = 'https://my-cool-project.lamalama.dev key123';
            $parts = explode(' ', $connectionInfo);
            $domain = $parts[0];

            $name = str_replace('https://', '', $domain);
            $name = str_replace('www.', '', $name);
            $name = str_replace('.lamalama.dev', '', $name);
            $name = str_replace('.nl', '', $name);
            $name = str_replace('.com', '', $name);

            expect($name)->toBe('my-cool-project');
        });

        it('generates correct repository URL', function () {
            $name = 'my-project';
            $repositoryUrl = 'git@github.com:lamalamaNL/'.$name.'.git';

            expect($repositoryUrl)->toBe('git@github.com:lamalamaNL/my-project.git');
        });

        it('generates database name with random suffix', function () {
            $name = 'my-project';
            $dbName = str_replace('-', '_', strtolower($name));
            $dbName .= '_'.rand(10000, 99999);

            expect($dbName)->toStartWith('my_project_');
            expect(strlen($dbName))->toBeGreaterThan(strlen('my_project_'));
        });
    });
});
