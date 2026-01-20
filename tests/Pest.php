<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Process\Process;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(Tests\TestCase::class)->in('Feature');
uses(Tests\TestCase::class)->in('Unit');
uses(Tests\TestCase::class)->in('Architecture');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeValidJson', function () {
    json_decode($this->value);

    return $this->and(json_last_error())->toBe(JSON_ERROR_NONE);
});

expect()->extend('toBeValidConnectionInfo', function () {
    // Pattern for staging: URL followed by connection key
    $stagingPattern = '/^https:\/\/[a-zA-Z0-9\-]+\.lamalama\.dev\s+[a-zA-Z0-9+\-\/]+$/';

    return $this->toMatch($stagingPattern);
});

expect()->extend('toBeValidProductionConnectionInfo', function () {
    // Pattern for production: any URL followed by connection key
    $productionPattern = '/^https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\s+[a-zA-Z0-9+\-\/]+$/';

    return $this->toMatch($productionPattern);
});

expect()->extend('toBeValidDomain', function () {
    return $this->toMatch('/^[a-zA-Z0-9\-]+\.lamalama\.dev$/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create a temporary directory for testing.
 */
function createTempDirectory(): string
{
    $tempDir = sys_get_temp_dir().'/clli_test_'.uniqid();
    mkdir($tempDir, 0777, true);

    return $tempDir;
}

/**
 * Clean up a temporary directory.
 */
function cleanupTempDirectory(string $path): void
{
    if (is_dir($path)) {
        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $path.'/'.$file;
            if (is_dir($filePath)) {
                cleanupTempDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($path);
    }
}

/**
 * Call a private or protected method on an object.
 */
function callPrivateMethod(object $obj, string $method, array $args = []): mixed
{
    $reflection = new ReflectionMethod($obj, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($obj, ...$args);
}

/**
 * Set a private or protected property on an object.
 */
function setPrivateProperty(object $obj, string $property, mixed $value): void
{
    $reflection = new ReflectionProperty($obj, $property);
    $reflection->setAccessible(true);
    $reflection->setValue($obj, $value);
}

/**
 * Get a private or protected property from an object.
 */
function getPrivateProperty(object $obj, string $property): mixed
{
    $reflection = new ReflectionProperty($obj, $property);
    $reflection->setAccessible(true);

    return $reflection->getValue($obj);
}

/**
 * Create a mock Guzzle client with predefined responses.
 */
function createMockGuzzleClient(array $responses = []): Client
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
 * Create a mock Process that returns predetermined output.
 */
function createMockProcess(int $exitCode = 0, string $output = '', string $errorOutput = ''): Process
{
    $process = new class($exitCode, $output, $errorOutput) extends Process
    {
        private int $mockExitCode;

        private string $mockOutput;

        private string $mockErrorOutput;

        public function __construct(int $exitCode, string $output, string $errorOutput)
        {
            $this->mockExitCode = $exitCode;
            $this->mockOutput = $output;
            $this->mockErrorOutput = $errorOutput;
            parent::__construct(['echo', 'mock']);
        }

        public function run(?callable $callback = null, array $env = []): int
        {
            return $this->mockExitCode;
        }

        public function isSuccessful(): bool
        {
            return $this->mockExitCode === 0;
        }

        public function getExitCode(): ?int
        {
            return $this->mockExitCode;
        }

        public function getOutput(): string
        {
            return $this->mockOutput;
        }

        public function getErrorOutput(): string
        {
            return $this->mockErrorOutput;
        }
    };

    return $process;
}

/**
 * Get the validation pattern for staging connection info.
 */
function getStagingConnectionPattern(): string
{
    return '/^https:\/\/[a-zA-Z0-9\-]+\.lamalama\.dev\s+[a-zA-Z0-9+\-\/]+$/';
}

/**
 * Get the validation pattern for production connection info.
 */
function getProductionConnectionPattern(): string
{
    return '/^https?:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,}\s+[a-zA-Z0-9+\-\/]+$/';
}
