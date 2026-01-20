<?php

use LamaLama\Clli\Console\Services\CliConfig;

beforeEach(function () {
    $this->tempDir = createTempDirectory();
    $this->originalHome = getenv('HOME');
    // Set HOME to temp directory for testing
    putenv('HOME='.$this->tempDir);
});

afterEach(function () {
    // Restore original HOME
    putenv('HOME='.$this->originalHome);
    cleanupTempDirectory($this->tempDir);
});

describe('CliConfig', function () {
    describe('initialization', function () {
        it('creates config directory if it does not exist', function () {
            $config = new CliConfig;

            expect(is_dir($this->tempDir.'/.clli'))->toBeTrue();
        });

        it('creates config file if it does not exist', function () {
            $config = new CliConfig;

            expect(file_exists($this->tempDir.'/.clli/config.json'))->toBeTrue();
        });

        it('creates config file with default structure', function () {
            $config = new CliConfig;

            $contents = json_decode(file_get_contents($this->tempDir.'/.clli/config.json'), true);

            expect($contents)->toHaveKey('created_at');
            expect($contents)->toHaveKey('updated_at');
        });
    });

    describe('read()', function () {
        it('returns empty array when config file does not exist', function () {
            // Create a config that points to a non-existent path first
            $config = new CliConfig;

            // Delete the file after initialization
            unlink($this->tempDir.'/.clli/config.json');

            expect($config->read())->toBe([]);
        });

        it('returns config data as array', function () {
            $config = new CliConfig;

            $data = $config->read();

            expect($data)->toBeArray();
            expect($data)->toHaveKey('created_at');
        });
    });

    describe('get()', function () {
        it('returns null for non-existent key', function () {
            $config = new CliConfig;

            expect($config->get('non_existent_key'))->toBeNull();
        });

        it('returns value for existing key', function () {
            $config = new CliConfig;
            $config->set('test_key', 'test_value');

            expect($config->get('test_key'))->toBe('test_value');
        });

        it('returns created_at timestamp', function () {
            $config = new CliConfig;

            expect($config->get('created_at'))->not->toBeNull();
        });
    });

    describe('set()', function () {
        it('stores a string value', function () {
            $config = new CliConfig;
            $config->set('api_key', 'my-secret-key');

            expect($config->get('api_key'))->toBe('my-secret-key');
        });

        it('stores an integer value', function () {
            $config = new CliConfig;
            $config->set('server_id', 12345);

            expect($config->get('server_id'))->toBe(12345);
        });

        it('stores an array value', function () {
            $config = new CliConfig;
            $config->set('servers', ['server1', 'server2']);

            expect($config->get('servers'))->toBe(['server1', 'server2']);
        });

        it('overwrites existing value', function () {
            $config = new CliConfig;
            $config->set('key', 'old_value');
            $config->set('key', 'new_value');

            expect($config->get('key'))->toBe('new_value');
        });

        it('updates the updated_at timestamp', function () {
            $config = new CliConfig;
            $initialUpdatedAt = $config->get('updated_at');

            sleep(1); // Ensure time difference
            $config->set('new_key', 'value');

            expect($config->get('updated_at'))->not->toBe($initialUpdatedAt);
        });

        it('persists data to file', function () {
            $config = new CliConfig;
            $config->set('persistent_key', 'persistent_value');

            // Create a new instance to read from file
            $newConfig = new CliConfig;

            expect($newConfig->get('persistent_key'))->toBe('persistent_value');
        });
    });

    describe('delete()', function () {
        it('removes an existing key', function () {
            $config = new CliConfig;
            $config->set('to_delete', 'value');

            expect($config->get('to_delete'))->toBe('value');

            $config->delete('to_delete');

            expect($config->get('to_delete'))->toBeNull();
        });

        it('does nothing when deleting non-existent key', function () {
            $config = new CliConfig;

            // Should not throw an error
            $config->delete('non_existent');

            expect($config->get('non_existent'))->toBeNull();
        });

        it('persists deletion to file', function () {
            $config = new CliConfig;
            $config->set('to_delete', 'value');
            $config->delete('to_delete');

            // Create a new instance to read from file
            $newConfig = new CliConfig;

            expect($newConfig->get('to_delete'))->toBeNull();
        });
    });

    describe('write()', function () {
        it('writes data to file in JSON format', function () {
            $config = new CliConfig;
            $config->set('test', 'value');

            $contents = file_get_contents($this->tempDir.'/.clli/config.json');

            expect($contents)->toBeValidJson();
        });

        it('writes pretty-printed JSON', function () {
            $config = new CliConfig;
            $config->set('test', 'value');

            $contents = file_get_contents($this->tempDir.'/.clli/config.json');

            // Pretty-printed JSON contains newlines
            expect($contents)->toContain("\n");
        });
    });
});

describe('CliConfig for project', function () {
    it('throws exception when path is not provided for project config', function () {
        expect(fn () => new CliConfig(forProject: true))
            ->toThrow(InvalidArgumentException::class);
    });

    it('creates project config file at specified path', function () {
        $projectPath = $this->tempDir.'/my-project';
        mkdir($projectPath, 0777, true);

        $config = new CliConfig(forProject: true, path: $projectPath);

        expect(file_exists($projectPath.'/.clli'))->toBeTrue();
    });

    it('stores and retrieves values from project config', function () {
        $projectPath = $this->tempDir.'/my-project';
        mkdir($projectPath, 0777, true);

        $config = new CliConfig(forProject: true, path: $projectPath);
        $config->set('project_setting', 'project_value');

        expect($config->get('project_setting'))->toBe('project_value');
    });

    it('keeps project config separate from global config', function () {
        $projectPath = $this->tempDir.'/my-project';
        mkdir($projectPath, 0777, true);

        // Set up global config
        $globalConfig = new CliConfig;
        $globalConfig->set('global_key', 'global_value');

        // Set up project config
        $projectConfig = new CliConfig(forProject: true, path: $projectPath);
        $projectConfig->set('project_key', 'project_value');

        // Global should not have project key
        expect($globalConfig->get('project_key'))->toBeNull();

        // Project should not have global key
        expect($projectConfig->get('global_key'))->toBeNull();
    });
});
