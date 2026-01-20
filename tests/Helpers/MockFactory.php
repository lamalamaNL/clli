<?php

namespace Tests\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Factory for creating mock objects used in tests.
 * Ensures no external services are called during testing.
 */
class MockFactory
{
    /**
     * Create a mock Guzzle client with predefined responses.
     *
     * @param  array<array{status?: int, headers?: array, body?: string}|Response>  $responses
     */
    public static function guzzleClient(array $responses = []): Client
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
     * Create a Guzzle response for successful API calls.
     */
    public static function successResponse(string $body = '', array $headers = []): Response
    {
        return new Response(200, $headers, $body);
    }

    /**
     * Create a Guzzle response for not found errors.
     */
    public static function notFoundResponse(string $body = '{"message": "Not Found"}'): Response
    {
        return new Response(404, [], $body);
    }

    /**
     * Create a Guzzle response for authentication errors.
     */
    public static function unauthorizedResponse(string $body = '{"message": "Bad credentials"}'): Response
    {
        return new Response(401, [], $body);
    }

    /**
     * Create a mock Process that returns predetermined output.
     */
    public static function process(int $exitCode = 0, string $output = '', string $errorOutput = ''): Process
    {
        return new class($exitCode, $output, $errorOutput) extends Process
        {
            private int $mockExitCode;

            private string $mockOutput;

            private string $mockErrorOutput;

            private bool $hasRun = false;

            public function __construct(int $exitCode, string $output, string $errorOutput)
            {
                $this->mockExitCode = $exitCode;
                $this->mockOutput = $output;
                $this->mockErrorOutput = $errorOutput;
                parent::__construct(['echo', 'mock']);
            }

            public function run(?callable $callback = null, array $env = []): int
            {
                $this->hasRun = true;

                if ($callback) {
                    if ($this->mockOutput) {
                        $callback(Process::OUT, $this->mockOutput);
                    }
                    if ($this->mockErrorOutput) {
                        $callback(Process::ERR, $this->mockErrorOutput);
                    }
                }

                return $this->mockExitCode;
            }

            public function start(?callable $callback = null, array $env = []): void
            {
                $this->hasRun = true;
            }

            public function wait(?callable $callback = null): int
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

            public function isRunning(): bool
            {
                return false;
            }

            public function isStarted(): bool
            {
                return $this->hasRun;
            }

            public function isTerminated(): bool
            {
                return $this->hasRun;
            }
        };
    }

    /**
     * Create a successful mock Process.
     */
    public static function successfulProcess(string $output = ''): Process
    {
        return self::process(0, $output);
    }

    /**
     * Create a failed mock Process.
     */
    public static function failedProcess(string $errorOutput = 'Command failed', int $exitCode = 1): Process
    {
        return self::process($exitCode, '', $errorOutput);
    }

    /**
     * Create a mock InputInterface for command testing.
     */
    public static function input(TestCase $testCase, array $arguments = [], array $options = []): MockObject
    {
        $input = $testCase->createMock(InputInterface::class);

        $input->method('getArgument')
            ->willReturnCallback(fn ($name) => $arguments[$name] ?? null);

        $input->method('getArguments')
            ->willReturn($arguments);

        $input->method('getOption')
            ->willReturnCallback(fn ($name) => $options[$name] ?? null);

        $input->method('getOptions')
            ->willReturn($options);

        $input->method('isInteractive')
            ->willReturn(false);

        return $input;
    }

    /**
     * Create a mock OutputInterface for command testing.
     */
    public static function output(TestCase $testCase, bool $decorated = false, bool $verbose = false): MockObject
    {
        $output = $testCase->createMock(OutputInterface::class);

        $output->method('isDecorated')
            ->willReturn($decorated);

        $output->method('isVerbose')
            ->willReturn($verbose);

        $writtenLines = [];
        $output->method('writeln')
            ->willReturnCallback(function ($line) use (&$writtenLines) {
                $writtenLines[] = $line;
            });

        $output->method('write')
            ->willReturnCallback(function ($text) use (&$writtenLines) {
                $writtenLines[] = $text;
            });

        return $output;
    }

    /**
     * Create a GitHub API response for listing files.
     */
    public static function githubFilesResponse(array $files): Response
    {
        $body = json_encode(array_map(fn ($name) => ['name' => $name, 'type' => 'file'], $files));

        return new Response(200, [], $body);
    }

    /**
     * Create a GitHub API response for file content.
     */
    public static function githubFileContentResponse(string $content): Response
    {
        return new Response(200, [], $content);
    }
}
