<?php

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class TestCase extends BaseTestCase
{
    protected ?string $tempDir = null;

    protected ?string $originalHome = null;

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            $this->cleanupDirectory($this->tempDir);
        }

        // Restore HOME environment variable if it was changed
        if ($this->originalHome !== null) {
            putenv('HOME='.$this->originalHome);
            $this->originalHome = null;
        }

        parent::tearDown();
    }

    /**
     * Create a temporary directory for testing.
     */
    protected function createTempDir(): string
    {
        $this->tempDir = sys_get_temp_dir().'/clli_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        return $this->tempDir;
    }

    /**
     * Set up a temporary HOME directory for config testing.
     */
    protected function setupTempHome(): string
    {
        $this->originalHome = getenv('HOME');
        $this->tempDir = $this->createTempDir();
        putenv('HOME='.$this->tempDir);

        return $this->tempDir;
    }

    /**
     * Clean up a directory recursively.
     */
    protected function cleanupDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path.'/'.$file;
            if (is_dir($filePath)) {
                $this->cleanupDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($path);
    }

    /**
     * Create a CLI application instance for testing.
     */
    protected function createApplication(): Application
    {
        $app = new Application('CLLI', '1.0.0-test');

        return $app;
    }

    /**
     * Create a mock Guzzle client with predefined responses.
     */
    protected function createMockGuzzleClient(array $responses = []): Client
    {
        $mockResponses = array_map(function ($response) {
            if ($response instanceof Response) {
                return $response;
            }

            return new Response(
                $response['status'] ?? 200,
                $response['headers'] ?? [],
                $response['body'] ?? ''
            );
        }, $responses);

        $mock = new MockHandler($mockResponses);
        $handlerStack = HandlerStack::create($mock);

        return new Client(['handler' => $handlerStack]);
    }

    /**
     * Call a private or protected method on an object.
     */
    protected function callPrivateMethod(object $obj, string $method, array $args = []): mixed
    {
        $reflection = new ReflectionMethod($obj, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($obj, ...$args);
    }

    /**
     * Set a private or protected property on an object.
     */
    protected function setPrivateProperty(object $obj, string $property, mixed $value): void
    {
        $reflection = new ReflectionProperty($obj, $property);
        $reflection->setAccessible(true);
        $reflection->setValue($obj, $value);
    }

    /**
     * Get a private or protected property from an object.
     */
    protected function getPrivateProperty(object $obj, string $property): mixed
    {
        $reflection = new ReflectionProperty($obj, $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($obj);
    }

    /**
     * Create input/output objects for command testing.
     */
    protected function createCommandIO(array $arguments = [], array $options = []): array
    {
        $inputArgs = array_merge($arguments, $options);
        $input = new ArrayInput($inputArgs);
        $input->setInteractive(false);
        $output = new BufferedOutput;

        return [$input, $output];
    }

    /**
     * Assert that a command is properly configured.
     */
    protected function assertCommandConfiguration(
        Command $command,
        string $expectedName,
        string $expectedDescription
    ): void {
        $this->assertEquals($expectedName, $command->getName());
        $this->assertEquals($expectedDescription, $command->getDescription());
    }
}
