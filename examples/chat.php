#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Client\ClientInterface;
use Icicle\Socket\Server\ServerInterface;
use Icicle\Socket\Server\ServerFactory;

// Connect using `nc localhost 60000`.

$coroutine = Coroutine\create(function (ServerInterface $server) {
    $clients = new SplObjectStorage();
    
    $handler = Coroutine\async(function (ClientInterface $client) use (&$clients) {
        $clients->attach($client);
        $name = $client->getRemoteAddress() . ':' . $client->getRemotePort();
        
        try {
            foreach ($clients as $stream) {
                if ($client !== $stream) {
                    $stream->write("{$name} connected.\n");
                }
            }

            yield $client->write("Welcome {$name}!\n");
            
            while ($client->isReadable()) {
                $data = trim(yield $client->read());
                
                if ("/exit" === $data) {
                    yield $client->end("Goodbye!\n");
                } elseif ('' !== $data) {
                    $message = "{$name}: {$data}\n";
                    foreach ($clients as $stream) {
                        if ($client !== $stream) {
                            $stream->write($message);
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            $client->close($exception);
        } finally {
            $clients->detach($client);
            foreach ($clients as $stream) {
                $stream->write("{$name} disconnected.\n");
            }
        }
    });
    
    while ($server->isOpen()) {
        $handler(yield $server->accept());
    }
}, (new ServerFactory())->create('127.0.0.1', 60000));

Loop\run();

