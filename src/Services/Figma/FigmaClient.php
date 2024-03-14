<?php

namespace LamaLama\Clli\Console\Services\Figma;

use GuzzleHttp\Client;

class FigmaClient
{

    public $figmaProjectId = ''; // TODO: Get the key from your config
    protected Client $client;
    public function __construct()
    {
        $token = '';
        $this->client = new Client([
            'base_uri' => 'https://api.figma.com',
            'headers' => [
                'X-FIGMA-TOKEN' => $token,
            ]
        ]);
    }

    public function get($url)
    {
        $response = $this->client->get($url);
        $body = json_decode($response->getBody(), true);
        var_dump($body);
    }
}
