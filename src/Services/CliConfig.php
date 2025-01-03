<?php

namespace LamaLama\Clli\Console\Services;

use stdClass;

class CliConfig
{
    private $configFilePath;

    /**
     * Initialize the config file path and create if needed
     */
    public function __construct()
    {
        $this->configFilePath = $this->resolvePath('~/.clli/config.json');
        $this->initializeFile();
    }

    /**
     * Resolve the full path, expanding ~ to home directory
     */
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

    /**
     * Create the config directory and file if they don't exist
     */
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

    /**
     * Read the config file contents
     */
    public function read()
    {
        if (! file_exists($this->configFilePath)) {
            return [];
        }

        $jsonContent = file_get_contents($this->configFilePath);

        return json_decode($jsonContent, true);
    }

    /**
     * Write data to the config file
     */
    public function write($data)
    {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->configFilePath, $jsonContent);
    }

    /**
     * Get a value from the config by key
     */
    public function get($key)
    {
        $data = $this->read();

        return isset($data[$key]) ? $data[$key] : null;
    }

    /**
     * Set a value in the config by key
     */
    public function set($key, $value)
    {
        $data = $this->read();
        $data[$key] = $value;
        $this->write($data);
    }

    /**
     * Delete a value from the config by key
     */
    public function delete($key)
    {
        $data = $this->read();

        if (isset($data[$key])) {
            unset($data[$key]);
            $this->write($data);
        }
    }
}
