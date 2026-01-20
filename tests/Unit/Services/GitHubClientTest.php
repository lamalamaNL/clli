<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use LamaLama\Clli\Console\Services\GitHubAuthException;
use LamaLama\Clli\Console\Services\GitHubClient;
use Tests\Helpers\MockFactory;

describe('GitHubClient', function () {
    describe('listFilesInDirectory()', function () {
        it('returns file names from API response', function () {
            $mockClient = MockFactory::guzzleClient([
                MockFactory::githubFilesResponse(['file1.php', 'file2.php', 'README.md']),
            ]);

            $client = new GitHubClient('test-token');
            setPrivateProperty($client, 'client', $mockClient);

            $result = $client->listFilesInDirectory('lamalamaNL/test-repo', 'src');

            expect($result)->toBe(['file1.php', 'file2.php', 'README.md']);
        });

        it('throws GitHubAuthException for 404 response', function () {
            // Create a client that will throw a ClientException with 404
            $mockHandler = new \GuzzleHttp\Handler\MockHandler([
                new ClientException(
                    'Not Found',
                    new Request('GET', '/repos/test/contents/path'),
                    MockFactory::notFoundResponse()
                ),
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
            $mockClient = new Client(['handler' => $handlerStack]);

            $client = new GitHubClient('test-token');
            setPrivateProperty($client, 'client', $mockClient);

            // 404 has a code, so it throws GitHubAuthException
            expect(fn () => $client->listFilesInDirectory('lamalamaNL/test-repo', 'nonexistent'))
                ->toThrow(GitHubAuthException::class);
        });

        it('throws GitHubAuthException on authentication error', function () {
            // Create a client that will throw a ClientException
            $mockHandler = new \GuzzleHttp\Handler\MockHandler([
                new ClientException(
                    'Unauthorized',
                    new Request('GET', '/repos/test/contents/path'),
                    MockFactory::unauthorizedResponse()
                ),
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
            $mockClient = new Client(['handler' => $handlerStack]);

            $client = new GitHubClient('invalid-token');
            setPrivateProperty($client, 'client', $mockClient);

            expect(fn () => $client->listFilesInDirectory('lamalamaNL/test-repo', 'src'))
                ->toThrow(GitHubAuthException::class);
        });

        it('returns error message on other failures', function () {
            // Create a client that will throw an exception without a code
            $mockHandler = new \GuzzleHttp\Handler\MockHandler([
                new \GuzzleHttp\Exception\ConnectException(
                    'Connection failed',
                    new Request('GET', '/repos/test/contents/path')
                ),
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
            $mockClient = new Client(['handler' => $handlerStack]);

            $client = new GitHubClient('test-token');
            setPrivateProperty($client, 'client', $mockClient);

            $result = $client->listFilesInDirectory('lamalamaNL/test-repo', 'src');

            expect($result)->toContain('Error:');
        });
    });

    describe('downloadFile()', function () {
        it('returns file contents on success', function () {
            $fileContent = '<?php echo "Hello World";';
            $mockClient = MockFactory::guzzleClient([
                MockFactory::githubFileContentResponse($fileContent),
            ]);

            $client = new GitHubClient('test-token');
            setPrivateProperty($client, 'client', $mockClient);

            $result = $client->downloadFile('lamalamaNL/test-repo', 'src/index.php');

            expect($result)->toBe($fileContent);
        });

        it('returns error message on failure', function () {
            $mockHandler = new \GuzzleHttp\Handler\MockHandler([
                new ClientException(
                    'Not Found',
                    new Request('GET', '/repos/test/contents/path'),
                    MockFactory::notFoundResponse()
                ),
            ]);
            $handlerStack = \GuzzleHttp\HandlerStack::create($mockHandler);
            $mockClient = new Client(['handler' => $handlerStack]);

            $client = new GitHubClient('test-token');
            setPrivateProperty($client, 'client', $mockClient);

            $result = $client->downloadFile('lamalamaNL/test-repo', 'nonexistent.php');

            expect($result)->toContain('Error:');
        });
    });

    describe('constructor', function () {
        it('stores the token', function () {
            $client = new GitHubClient('my-secret-token');

            $token = getPrivateProperty($client, 'token');

            expect($token)->toBe('my-secret-token');
        });

        it('creates a Guzzle client', function () {
            $client = new GitHubClient('test-token');

            $guzzleClient = getPrivateProperty($client, 'client');

            expect($guzzleClient)->toBeInstanceOf(Client::class);
        });
    });
});
