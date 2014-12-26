#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\StreamSocket\Client;
use Icicle\StreamSocket\Server;

// Connect using `nc localhost 60000`.

$coroutine = Coroutine::call(function (Server $server) {
    $handler = Coroutine::async(function (Client $client) {
        try {
            yield $client->ready();
            
            yield $client->write("Want to play shadow? (Type 'exit' to quit)\n");
			
            while ($client->isReadable()) {
                $data = (yield $client->read());
                
                yield $client->write("Binary: ".bin2hex($data)."\n");
                
                if ("exit\n" === $data) {
                    yield $client->write("Goodbye!\n");
                    $client->close();
                } else {
                    yield $client->write("Plaintext: ".$data);
                }
            }
        } catch (Exception $e) {
            $client->close();
        }
    });
    
    while ($server->isOpen()) {
        $handler(yield $server->accept());
    }
}, Server::create('localhost', 60000));

Loop::run();
