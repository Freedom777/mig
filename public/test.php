<?php
require __DIR__.'/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

try {
    $connection = new AMQPStreamConnection(
        '127.0.0.1', // или 'rabbitmq' если через docker-compose
        5672,
        'guest',
        'guest'
    );

    $channel = $connection->channel();

    $channel->queue_declare('test_queue', false, false, false, false);

    $msg = new AMQPMessage('Hello from direct test!');
    $channel->basic_publish($msg, '', 'test_queue');

    echo "✅ Message sent successfully\n";

    $channel->close();
    $connection->close();
} catch (\Exception $e) {
    echo "❌ Connection failed: ".$e->getMessage()."\n";
}
