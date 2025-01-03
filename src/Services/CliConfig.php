<?php

namespace LamaLama\Clli\Console\Services;

use stdClass;

class CliConfig
{
    private $configFilePath;

    public function __construct()
    {
        $this->configFilePath = $this->resolvePath('~/.clli/config.json');
        $this->initializeFile();
    }

    private function resolvePath($path)
    {
        if (strpos($path, '~') === 0) {
            $homeDirectory = getenv('HOME');
            if ($homeDirectory) {
                $path = $homeDirectory.substr($path, 1);
            } else {
                throw new Exception('Unable to resolve the home directory.');
            }
        }

        return $path;
    }

    private function initializeFile()
    {
        $dir = dirname($this->configFilePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (! file_exists($this->configFilePath)) {
            file_put_contents($this->configFilePath, json_encode(new stdClass, JSON_PRETTY_PRINT));
        }
    }

    // Read JSON file and decode to PHP array
    public function read()
    {
        if (! file_exists($this->configFilePath)) {
            return [];
        }

        $jsonContent = file_get_contents($this->configFilePath);

        return json_decode($jsonContent, true);
    }

    // Write PHP array to JSON file
    public function write($data)
    {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->configFilePath, $jsonContent);
    }

    // Get value by key from JSON file
    public function get($key)
    {
        $data = $this->read();

        return isset($data[$key]) ? $data[$key] : null;
    }

    // Set value by key in JSON file
    public function set($key, $value)
    {
        $data = $this->read();
        $data[$key] = $value;
        $this->write($data);
    }

    // Delete value by key in JSON file
    public function delete($key)
    {
        $data = $this->read();

        if (isset($data[$key])) {
            unset($data[$key]);
            $this->write($data);
        }
    }
}
