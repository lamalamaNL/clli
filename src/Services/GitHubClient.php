<?php


namespace LamaLama\Clli\Console\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;

class GitHubClient
{

    private $token;
    private $client;
    private $baseUrl = 'https://api.github.com';

    public function __construct($token)
    {
        $this->token = $token;
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'token ' . $this->token,
                'Accept' => 'application/vnd.github.v3.raw'
            ]
        ]);
    }

    public function listFilesInDirectory($repo, $path)
    {
        try {
            $response = $this->client->request('GET', "/repos/{$repo}/contents/{$path}");
            $body = json_decode($response->getBody(), true);

            if (isset($body['message']) && $body['message'] === 'Not Found') {
                return 'Directory not found.';
            }

            return array_map(function ($file) {
                return $file['name'];
            }, $body);

        } catch (GuzzleException $e) {
            if ($e->getCode()) {
                throw new GitHubAuthException($e->getMessage());
            }
            return 'Error: ' . $e->getMessage();
        }
    }

    public function downloadFile($repo, $path)
    {
        try {
            $response = $this->client->request('GET', "/repos/{$repo}/contents/{$path}");
            return $response->getBody()->getContents();
        } catch (GuzzleException $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}


