<?php

namespace LamaLama\Clli\Console\Services\Websocket\InternalFigmaConnect;

use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Protocols\Http\Response;
use Workerman\Worker;

class FigmaMessenger
{
    protected $clients;
    protected OutputInterface $output;
    protected Worker $socket;
    protected Worker $webserver;


    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->initSocket();
        $this->initWebserver();
        global $argv;
        $argv[1] = 'start';
        var_dump($argv);
        Worker::runAll();
    }

    private function initSocket()
    {
        $this->socket = new Worker('websocket://0.0.0.0:5000');
        $this->output->write('Listening for input from figma plugin');

        // Emitted when new connection come
        $this->socket->onConnect = function ($connection) {
            $this->output->write('New connection');
        };

        // Emitted when data received
        $this->socket->onMessage = function ($connection, $data)
        {
            $this->output->write('New message');
            var_dump($data);
            $connection->send('Hello from server');
        };

        // Emitted when connection closed
        $this->socket->onClose = function ($connection) {
            echo "Connection closed\n";
        };
    }

    private function initWebserver() : void
    {
        $this->webserver = new Worker('http://0.0.0.0:8989');
//        $this->webserver->transport = 'ssl';
        // 4 processes
        $this->webserver->count = 4;

        // Emitted when data received
        $this->webserver->onMessage = function ($connection, $request) {
//            var_dump($request->method(), $request->path());
            //$request->get();
            //$request->post();
            //$request->header();
            //$request->cookie();
            //$request->session();
            //$request->uri();
            //;
            //$request->method();
            $emptyResponse = new Response(404, [], '');
//            $segments = array_filter(explode('/', $request->path()), fn($p) => !!$p);
//            var_dump($segments);
            switch ($request->method()) {
                case 'GET':
                    return match ($request->path()) {
                        '/status' => $connection->send($this->status()),
                        '/components' => $connection->send($this->listAllComponents()),
                        '/parts' => $connection->send($this->listComponentsResponse('parts')),
                        '/blocks' => $connection->send($this->listComponentsResponse('blocks')),
                        '/sections' => $connection->send($this->listComponentsResponse('sections')),
                        '/templates' => $connection->send($this->listComponentsResponse('templates')),
                        default => $connection->send($emptyResponse),
                    };
                case 'POST':
                    // Create a component

                case 'PUT':
                    // Update a component?

                case 'PATCH':
                    // Fill POST with ACF content
                    /*
                     * This will not work i guess
                     * Other aproach:
                     * Create a ajax endpoint in the boilerplate
                     * where we can send ACF content info. We t
                     */

                    /*
                     * Widget needs to have 3 tabs
                     * Structure: Figma component structure vs CLI code stucture
                     * - Check if component exists
                     * - Create component
                     * - Generate API component
                     * - .....
                     * Content:
                     * - A page in figma vs A post in
                     * - Works with the custom ajax endpoint
                     * - Per page: connect to post id
                     * - Loop trough all component and get content. Send this to AJAX endpoint to fill data in ACF fields
                     * Style:
                     * - Read all variables from figma.
                     * - Display as json
                     * - Discuss with Aak how this must work.
                     */

                    /*
                     * Idea for Component library
                     * - Wordpress install with all components
                     * - Tell CLI where is lives and we can use all features from Structure
                     * - Simple copy and past per component
                     *
                     */
                case 'PATCH':
                $emptyResponse = new Response(404, $this->headers(), '');
                $connection->send($emptyResponse);



            }

            // Send data to client

            $connection->send($emptyResponse);

        };
    }

    public function status()
    {
        $response = new Response();
        $response->withHeaders($this->headers());
        $response->header('Content-Type', 'application/json');
        $currentDirectory = getcwd();
        $repo = pathinfo($currentDirectory);
        $response->withBody(json_encode(['status' => 'connected', 'repo' => $repo['basename']]));
        return $response;
    }

    private function listComponentsResponse($type)
    {
        $response = new Response();
        $response->withHeaders($this->headers());
        $response->header('Content-Type', 'application/json');
        $response->withBody(json_encode(['data' => $this->listComponents($type)]));
        return $response;
    }

    private function listAllComponents()
    {
        $types = ['parts', 'blocks', 'sections', 'templates'];
        $result = [];
        foreach ($types as $type) {
            $result[$type] = $this->listComponents($type);
        }
        $response = new Response();
        $response->withHeaders($this->headers());
        $response->header('Content-Type', 'application/json');

        $response->withBody(json_encode(['data' => $result]));
        return $response;
    }

    private function listComponents($type)
    {
        if (!in_array($type, ['parts', 'blocks', 'sections', 'templates'])) {
            return [];

        }
        // Get the current working directory
        $currentDirectory = getcwd();

        // Scan the current directory and get all files and directories
        $files = scandir($currentDirectory . '/components/' . $type);

        // Filter out the '.' and '..' entries which represent the current and parent directory
        $files = array_filter($files, function($file) {
            return $file !== '.' && $file !== '..';
        });
        $files = array_filter($files, function($file) {
            return $file[0] !== '.';
        });

        // Optionally, you can further filter to only include files (exclude directories)
//        $files = array_filter($files, function($file) use ($currentDirectory) {
//            return is_file($currentDirectory . DIRECTORY_SEPARATOR . $file);
//        });



        return array_values($files);
    }

    public function headers()
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
        ];
    }


}
