#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerFactory;
use Icicle\Socket\Server\Server;

// Connect using `nc localhost 60000`.

$generator = function (Server $server) {
    $handler = Coroutine::async(function (ClientInterface $client) {
        try {
            yield $client->write("Want to play shadow? (Type 'exit' to quit)\n");
			
            while ($client->isReadable()) {
                $data = (yield $client->read());
                
                $data = trim($data, "\n");
                
                if ("exit" === $data) {
                    yield $client->end("Goodbye!\n");
                } else {
                    yield $client->write("Echo: {$data}\n");
                }
            }
        } catch (Exception $e) {
            echo "Client error: {$e->getMessage()}\n";
            $client->close();
        }
    });
    
    echo "Echo server running on {$server->getAddress()}:{$server->getPort()}\n";
    
    while ($server->isOpen()) {
        try {
            $handler(yield $server->accept());
        } catch (Exception $e) {
            echo "Error accepting client: {$e->getMessage()}\n";
        }
    }
};

$coroutine = new Coroutine($generator((new ServerFactory())->create('localhost', 60000)));

Loop::run();
