#!/usr/bin/php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Client;
use Icicle\Socket\Server;

$server = Server::create('localhost', 8080, ['backlog' => 1024]);

$coroutine = Coroutine::async(function (Server $server) {
    $coroutine = Coroutine::async(function (Client $client) {
        try {
            $data = (yield $client->read());
            
            $microtime = sprintf("%0.4f", microtime(true));
            $message = "Received the following request ({$microtime}):\n\n{$data}";
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
    });
    
    while ($server->isOpen()) {
        try {
            $coroutine(yield $server->accept());
        } catch (Exception $exception) {
            echo "Error: Could not accept client: {$exception->getMessage()}";
        }
    }
});

$coroutine($server)->cleanup(function () use ($server) {
    $server->close();
});

Loop::schedule(function () { echo "Server started.\n"; });

Loop::run();
