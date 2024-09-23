<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

$socket = new Worker('websocket://0.0.0.0:5000');
echo('Listening for input from figma plugin');

// Emitted when new connection come
$socket->onConnect = function ($connection) {
    echo "New connection\n";
};

// Emitted when data received
$socket->onMessage = function ($connection, $data) {
    // Send hello $data
    $connection->send('Hello ' . $data);
};

// Emitted when connection closed
$socket->onClose = function ($connection) {
    echo "Connection closed\n";
};

Worker::runAll();
