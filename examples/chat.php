#!/usr/bin/php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\StreamSocket\Client;
use Icicle\StreamSocket\Server;

$coroutine = Coroutine::call(function () {
    $server = Server::create('localhost', 9898);
    $clients = [];
    
    $handler = Coroutine::async(function (Client $client) use (&$clients) {
        $clients[$client->getId()] = $client;
        $name = $client->getRemoteAddress() . ':' . $client->getRemotePort();
        
        try {
            yield $client->ready();
            
            yield $client->write("Welcome {$name}!\r\n");
            
            while ($client->isOpen()) {
                $data = (yield $client->read());
                
                $message = "{$name}: {$data}";
                
                foreach ($clients as $otherClient) {
                    if ($otherClient !== $client) {
                        $otherClient->write($message);
                    }
                }
                
                if ("exit\r\n" === $data) {
                    yield $client->end("Goodbye!\r\n");
                }
            }
        } catch (Exception $exception) {
            $client->close($exception);
        } finally {
            unset($clients[$client->getId()]);
        }
    });
    
    while ($server->isOpen()) {
        $handler(yield $server->accept());
    }
});

Loop::run();
