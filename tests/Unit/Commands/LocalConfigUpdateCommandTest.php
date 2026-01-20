<?php

use LamaLama\Clli\Console\LocalConfigUpdateCommand;
use Symfony\Component\Console\Application;

describe('LocalConfigUpdateCommand', function () {
    describe('command configuration', function () {
        it('has correct command name', function () {
            $command = new LocalConfigUpdateCommand;

            expect($command->getName())->toBe('config:update');
        });

        it('has correct description', function () {
            $command = new LocalConfigUpdateCommand;

            expect($command->getDescription())->toBe('Update a value in the CLLI config file');
        });

        it('can be registered to application', function () {
            $app = new Application;
            $app->add(new LocalConfigUpdateCommand);

            expect($app->has('config:update'))->toBeTrue();
        });

        it('extends BaseCommand', function () {
            $command = new LocalConfigUpdateCommand;

            expect($command)->toBeInstanceOf(\LamaLama\Clli\Console\BaseCommand::class);
        });
    });

    describe('getConfigHint()', function () {
        it('returns hint for forge_token', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['forge_token']);

            expect($result)->toBe('Get this from https://forge.laravel.com/user/profile#/api');
        });

        it('returns hint for forge_server_id', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['forge_server_id']);

            expect($result)->toBe('Select a server from your Forge account');
        });

        it('returns hint for forge_organization_id', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['forge_organization_id']);

            expect($result)->toBe('Found in the Forge dashboard URL when viewing an organization');
        });

        it('returns hint for cloudflare_token', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['cloudflare_token']);

            expect($result)->toBe("Generate via 'Create Token' at https://dash.cloudflare.com/profile/api-tokens");
        });

        it('returns hint for cloudflare_zone_id', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['cloudflare_zone_id']);

            expect($result)->toBe('Found in the Overview tab of your domain on https://dash.cloudflare.com');
        });

        it('returns hint for wp_migrate_license_key', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['wp_migrate_license_key']);

            expect($result)->toBe('Available in your WP Migrate account at https://deliciousbrains.com/my-account/licenses');
        });

        it('returns hint for openai_api_key', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['openai_api_key']);

            expect($result)->toBe('Get this from https://platform.openai.com/api-keys');
        });

        it('returns hint for public_key_filename', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['public_key_filename']);

            expect($result)->toBe('The filename of your SSH key in ~/.ssh/ (without .pub extension)');
        });

        it('returns empty string for unknown key', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['unknown_key']);

            expect($result)->toBe('');
        });

        it('returns empty string for custom key', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'getConfigHint', ['my_custom_setting']);

            expect($result)->toBe('');
        });
    });

    describe('isLongValueKey()', function () {
        it('returns true for forge_token', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['forge_token']);

            expect($result)->toBeTrue();
        });

        it('returns true for cloudflare_token', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['cloudflare_token']);

            expect($result)->toBeTrue();
        });

        it('returns true for wp_migrate_license_key', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['wp_migrate_license_key']);

            expect($result)->toBeTrue();
        });

        it('returns true for openai_api_key', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['openai_api_key']);

            expect($result)->toBeTrue();
        });

        it('returns false for forge_server_id', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['forge_server_id']);

            expect($result)->toBeFalse();
        });

        it('returns false for cloudflare_zone_id', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['cloudflare_zone_id']);

            expect($result)->toBeFalse();
        });

        it('returns false for public_key_filename', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['public_key_filename']);

            expect($result)->toBeFalse();
        });

        it('returns false for unknown keys', function () {
            $command = new LocalConfigUpdateCommand;

            $result = callPrivateMethod($command, 'isLongValueKey', ['some_random_key']);

            expect($result)->toBeFalse();
        });
    });
});
