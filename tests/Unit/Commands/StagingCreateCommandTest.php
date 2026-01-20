<?php

use LamaLama\Clli\Console\StagingCreateCommand;
use Symfony\Component\Console\Application;

describe('StagingCreateCommand', function () {
    describe('command configuration', function () {
        it('has correct command name', function () {
            $command = new StagingCreateCommand;

            expect($command->getName())->toBe('lamapress:staging');
        });

        it('has correct description', function () {
            $command = new StagingCreateCommand;

            expect($command->getDescription())
                ->toBe('Create a new staging environment for a WordPress site on Laravel Forge');
        });

        it('has optional subdomain argument', function () {
            $command = new StagingCreateCommand;
            $definition = $command->getDefinition();

            expect($definition->hasArgument('subdomain'))->toBeTrue();
            expect($definition->getArgument('subdomain')->isRequired())->toBeFalse();
        });

        it('can be registered to application', function () {
            $app = new Application;
            $app->add(new StagingCreateCommand);

            expect($app->has('lamapress:staging'))->toBeTrue();
        });

        it('extends BaseCommand', function () {
            $command = new StagingCreateCommand;

            expect($command)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        });
    });

    describe('fullDomain()', function () {
        it('combines subdomain with domain suffix', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'myproject');

            $result = callPrivateMethod($command, 'fullDomain');

            expect($result)->toBe('myproject.lamalama.dev');
        });

        it('handles hyphens in subdomain', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'my-cool-project');

            $result = callPrivateMethod($command, 'fullDomain');

            expect($result)->toBe('my-cool-project.lamalama.dev');
        });
    });

    describe('dbName()', function () {
        it('generates database name with prefix and timestamp', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'test');

            $result = callPrivateMethod($command, 'dbName');

            // The slug removes dots and combines, so test.lamalama.dev becomes testlamalamadev
            expect($result)->toStartWith('db_testlamalama');
            expect(strlen($result))->toBeGreaterThan(16);
        });

        it('truncates long names to fit MySQL limits', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'verylongsubdomainname');

            $result = callPrivateMethod($command, 'dbName');

            // Should be truncated then have timestamp added
            expect(strlen($result))->toBeLessThanOrEqual(64);
        });

        it('returns same value on subsequent calls', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'test');

            $first = callPrivateMethod($command, 'dbName');
            $second = callPrivateMethod($command, 'dbName');

            expect($first)->toBe($second);
        });
    });

    describe('dbUsername()', function () {
        it('generates database username with prefix', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'test');

            $result = callPrivateMethod($command, 'dbUsername');

            // The slug removes dots and combines
            expect($result)->toStartWith('dbu_testlamalama');
        });

        it('returns same value on subsequent calls', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'test');

            $first = callPrivateMethod($command, 'dbUsername');
            $second = callPrivateMethod($command, 'dbUsername');

            expect($first)->toBe($second);
        });
    });

    describe('dbPassword()', function () {
        it('generates 32 character password', function () {
            $command = new StagingCreateCommand;

            $result = callPrivateMethod($command, 'dbPassword');

            expect(strlen($result))->toBe(32);
        });

        it('returns same value on subsequent calls', function () {
            $command = new StagingCreateCommand;

            $first = callPrivateMethod($command, 'dbPassword');
            $second = callPrivateMethod($command, 'dbPassword');

            expect($first)->toBe($second);
        });

        it('generates alphanumeric password', function () {
            $command = new StagingCreateCommand;

            $result = callPrivateMethod($command, 'dbPassword');

            expect($result)->toMatch('/^[a-zA-Z0-9]+$/');
        });
    });

    describe('siteIsolatedName()', function () {
        it('generates isolated username with prefix', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'test');

            $result = callPrivateMethod($command, 'siteIsolatedName');

            // The slug removes dots, so test.lamalama.dev becomes u_testlamalamadev
            expect($result)->toStartWith('u_testlamalama');
        });

        it('truncates to maximum 32 characters', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'verylongsubdomainname');

            $result = callPrivateMethod($command, 'siteIsolatedName');

            expect(strlen($result))->toBeLessThanOrEqual(32);
        });

        it('returns same value on subsequent calls', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'subdomain', 'test');

            $first = callPrivateMethod($command, 'siteIsolatedName');
            $second = callPrivateMethod($command, 'siteIsolatedName');

            expect($first)->toBe($second);
        });
    });

    describe('wpUser()', function () {
        it('returns default admin username', function () {
            $command = new StagingCreateCommand;

            $result = callPrivateMethod($command, 'wpUser');

            expect($result)->toBe('Lama Lama');
        });
    });

    describe('wpPassword()', function () {
        it('generates 9 character password', function () {
            $command = new StagingCreateCommand;

            $result = callPrivateMethod($command, 'wpPassword');

            expect(strlen($result))->toBe(9);
        });

        it('returns same value on subsequent calls', function () {
            $command = new StagingCreateCommand;

            $first = callPrivateMethod($command, 'wpPassword');
            $second = callPrivateMethod($command, 'wpPassword');

            expect($first)->toBe($second);
        });
    });

    describe('wpUserEmail()', function () {
        it('returns default admin email', function () {
            $command = new StagingCreateCommand;

            $result = callPrivateMethod($command, 'wpUserEmail');

            expect($result)->toBe('wordpress@lamalama.nl');
        });
    });

    describe('calulateRepo()', function () {
        it('returns already set repo if available', function () {
            $command = new StagingCreateCommand;
            setPrivateProperty($command, 'repo', 'lamalamaNL/custom-repo');

            $result = callPrivateMethod($command, 'calulateRepo');

            expect($result)->toBe('lamalamaNL/custom-repo');
        });
    });

    describe('constants', function () {
        it('has correct domain suffix', function () {
            $reflection = new ReflectionClass(StagingCreateCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['DOMAIN_SUFFIX'])->toBe('lamalama.dev');
        });

        it('has correct GitHub organization', function () {
            $reflection = new ReflectionClass(StagingCreateCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['GITHUB_ORG'])->toBe('lamalamaNL');
        });

        it('has correct default branch', function () {
            $reflection = new ReflectionClass(StagingCreateCommand::class);
            $constants = $reflection->getConstants();

            expect($constants['DEFAULT_BRANCH'])->toBe('develop');
        });
    });
});
