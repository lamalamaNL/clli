<?php

use LamaLama\Clli\Console\ProductionPullCommand;
use Symfony\Component\Console\Application;

describe('ProductionPullCommand', function () {
    describe('command configuration', function () {
        it('has correct command name', function () {
            $command = new ProductionPullCommand;

            expect($command->getName())->toBe('production:pull');
        });

        it('has correct description', function () {
            $command = new ProductionPullCommand;

            expect($command->getDescription())->toBe('Pull a production environment');
        });

        it('has required connection_info argument', function () {
            $command = new ProductionPullCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('connection_info'))->toBeTrue();
            expect($definition->getArgument('connection_info')->isRequired())->toBeTrue();
        });

        it('has required name argument', function () {
            $command = new ProductionPullCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('name'))->toBeTrue();
            expect($definition->getArgument('name')->isRequired())->toBeTrue();
        });

        it('has optional repository_url option', function () {
            $command = new ProductionPullCommand;
            $definition = $command->getDefinition();

            expect($definition->hasOption('repository_url'))->toBeTrue();
        });

        it('can be registered to application', function () {
            $app = new Application;
            $app->add(new ProductionPullCommand);

            expect($app->has('production:pull'))->toBeTrue();
        });

        it('extends BaseCommand', function () {
            $command = new ProductionPullCommand;

            expect($command)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        });
    });

    describe('connection info validation pattern', function () {
        it('accepts valid production connection info', function () {
            $pattern = getProductionConnectionPattern();
            $validInputs = [
                'https://www.clientsite.nl qQSr+EVrJ83uIkME/zQiCBb4V/nVaG1dzh5vmqEq',
                'https://clientsite.com abc123def456',
                'https://sub.domain.co.uk key-with-dashes',
                'http://example.org simplekey',
                'https://my-client.nl key123',
            ];

            foreach ($validInputs as $input) {
                expect(preg_match($pattern, $input))->toBe(1, "Should match: $input");
            }
        });

        it('rejects invalid production connection info', function () {
            $pattern = getProductionConnectionPattern();
            $invalidInputs = [
                'https://localhost key123', // localhost without TLD
                'https://example key123', // no domain extension
                'https://example.nl', // missing key
                'example.nl key123', // missing protocol
            ];

            foreach ($invalidInputs as $input) {
                expect(preg_match($pattern, $input))->toBe(0, "Should not match: $input");
            }
        });
    });

    describe('project name extraction', function () {
        it('extracts name from simple domain', function () {
            $domain = 'https://www.clientsite.nl';
            $suggestedName = preg_replace('/^https?:\/\//', '', $domain);
            $suggestedName = preg_replace('/^www\./', '', $suggestedName);
            $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName);

            expect($suggestedName)->toBe('clientsite');
        });

        it('extracts name from domain with subdomain', function () {
            $domain = 'https://shop.clientsite.com';
            $suggestedName = preg_replace('/^https?:\/\//', '', $domain);
            $suggestedName = preg_replace('/^www\./', '', $suggestedName);
            $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName);

            expect($suggestedName)->toBe('shop.clientsite');
        });

        it('handles .co.uk and similar TLDs', function () {
            $domain = 'https://example.co.uk';
            $suggestedName = preg_replace('/^https?:\/\//', '', $domain);
            $suggestedName = preg_replace('/^www\./', '', $suggestedName);
            $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName);
            $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName); // Second pass for .co.uk

            expect($suggestedName)->toBe('example');
        });

        it('removes www prefix', function () {
            $domain = 'https://www.example.nl';
            $suggestedName = preg_replace('/^https?:\/\//', '', $domain);
            $suggestedName = preg_replace('/^www\./', '', $suggestedName);
            $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName);

            expect($suggestedName)->toBe('example');
        });

        it('handles http protocol', function () {
            $domain = 'http://example.org';
            $suggestedName = preg_replace('/^https?:\/\//', '', $domain);
            $suggestedName = preg_replace('/^www\./', '', $suggestedName);
            $suggestedName = preg_replace('/\.[a-zA-Z]{2,}$/', '', $suggestedName);

            expect($suggestedName)->toBe('example');
        });
    });

    describe('connection info parsing', function () {
        it('extracts domain from connection info', function () {
            $connectionInfo = 'https://www.clientsite.nl key123';
            $parts = explode(' ', $connectionInfo);

            expect($parts[0])->toBe('https://www.clientsite.nl');
        });

        it('extracts connection key from connection info', function () {
            $connectionInfo = 'https://clientsite.com qQSr+EVrJ83uIkME';
            $parts = explode(' ', $connectionInfo);

            expect($parts[1])->toBe('qQSr+EVrJ83uIkME');
        });

        it('generates correct repository URL from name', function () {
            $name = 'clientsite';
            $repositoryUrl = 'git@github.com:lamalamaNL/'.$name.'.git';

            expect($repositoryUrl)->toBe('git@github.com:lamalamaNL/clientsite.git');
        });

        it('generates database name with underscores', function () {
            $name = 'my-client-site';
            $dbName = str_replace('-', '_', strtolower($name));
            $dbName .= '_'.rand(10000, 99999);

            expect($dbName)->toStartWith('my_client_site_');
        });
    });
});
