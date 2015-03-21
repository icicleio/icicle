#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\ClientInterface;
use Icicle\Socket\Server;

// Connect using `nc localhost 60000`.

$coroutine = Coroutine::call(function (Server $server) {
    $clients = new SplObjectStorage();
    
    $handler = Coroutine::async(function (ClientInterface $client) use (&$clients) {
        $clients->attach($client);
        $name = $client->getRemoteAddress() . ':' . $client->getRemotePort();
        
        try {
            yield $client->write("Welcome {$name}!\r\n");
            
            while ($client->isReadable()) {
                $data = trim((yield $client->read()), "\n");
                
                if ("exit" === $data) {
                    yield $client->end("Goodbye!\r\n");
                    $message = "{$name} disconnected.\n";
                } else {
                    $message = "{$name}: {$data}\n";
                }
                
                foreach ($clients as $stream) {
                    if ($client !== $stream) {
                        $stream->write($message);
                    }
                }
            }
        } catch (Exception $exception) {
            $client->close($exception);
        } finally {
            $clients->detach($client);
        }
    });
    
    while ($server->isOpen()) {
        $handler(yield $server->accept());
    }
}, Server::create('127.0.0.1', 60000));

Loop::run();
