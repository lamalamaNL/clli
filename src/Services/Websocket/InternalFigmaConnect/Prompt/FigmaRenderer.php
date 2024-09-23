<?php

namespace LamaLama\Clli\Console\Services\Websocket\InternalFigmaConnect\Prompt;

use Laravel\Prompts\Themes\Default\Renderer;
use Workerman\Worker;

class FigmaRenderer extends Renderer
{

    protected $socket;
    public function __invoke() : string
    {
        $this->line('Testing 123');


        $this->socket = new Worker('websocket://0.0.0.0:5005');
//        $this->output->write('Listening for input from figma plugin');

        // Emitted when new connection come
        $this->socket->onConnect = function ($connection) {
            echo('new connection');
            var_dump($connection);
//            $this->output->write('New connection');
        };

        // Emitted when data received
        $this->socket->onMessage = function ($connection, $data)
        {
//            $this->output->write('New message');
            var_dump($data);
//            $connection->send('Hello from server');
        };

// Emitted when connection closed
        $this->socket->onClose = function ($connection) {
            echo "Connection closed\n";
        };
//        global $argv;
//        $argv[1] = 'start';
//        var_dump($argv);
//        Worker::runAll();
        $this->socket->run();
        $this->socket->listen();







        return 'test';
    }

}
