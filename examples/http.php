#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerInterface;
use Icicle\Socket\Server\ServerFactory;

$server = (new ServerFactory())->create('127.0.0.1', 8080, ['backlog' => 1024]);

$generator = function (ServerInterface $server) {
    $generator = function (ClientInterface $client) {
        try {
            $data = (yield $client->read());
            
            $microtime = sprintf("%0.4f", microtime(true));
            $message = "Received the following request ({$microtime}):\r\n\r\n{$data}";
            $length = strlen($message);
            
            $data  = "HTTP/1.1 200 OK\r\n";
            $data .= "Content-Type: text/plain\r\n";
            $data .= "Content-Length: {$length}\r\n";
            $data .= "Connection: close\r\n";
            $data .= "\r\n";
            $data .= $message;
            
            yield $client->write($data);
        } finally {
            $client->close();
        }
    };
    
    while ($server->isOpen()) {
        $coroutine = new Coroutine($generator(yield $server->accept()));
    }
};

$coroutine = new Coroutine($generator($server));

$coroutine->cleanup(function () use ($server) {
    $server->close();
});

echo "Server started.\n";

Loop\run();
