<?php

namespace LamaLama\Clli\Console\Services;

class CliConfig
{
    private $configFilePath;

    private $projectConfigFilePath;

    private bool $forProject = false;

    /**
     * Initialize the config file paths and create if needed
     */
    public function __construct(bool $forProject = false, ?string $path = null)
    {
        $this->forProject = $forProject;

        if ($forProject && $path === null) {
            throw new \InvalidArgumentException('Path must be provided when forProject is true');
        }

        $this->configFilePath = $this->resolvePath('~/.clli/config.json');
        $this->projectConfigFilePath = $path.'/.clli';

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
        if ($this->forProject) {
            if (! file_exists($this->projectConfigFilePath)) {
                $initialData = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                file_put_contents($this->projectConfigFilePath, json_encode($initialData, JSON_PRETTY_PRINT));
            }
        } else {
            $dir = dirname($this->configFilePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            if (! file_exists($this->configFilePath)) {
                $initialData = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                file_put_contents($this->configFilePath, json_encode($initialData, JSON_PRETTY_PRINT));
            }
        }
    }

    /**
     * Get the active config file path
     */
    private function getActivePath()
    {
        return $this->forProject ? $this->projectConfigFilePath : $this->configFilePath;
    }

    /**
     * Read the config file contents
     */
    public function read()
    {
        $path = $this->getActivePath();

        if (! file_exists($path)) {
            return [];
        }

        $jsonContent = file_get_contents($path);

        return json_decode($jsonContent, true) ?? [];
    }

    /**
     * Write data to the config file
     */
    public function write($data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);
        file_put_contents($this->getActivePath(), $jsonContent);
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
