#!/usr/bin/php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\LocalClient;

// Connect using `nc localhost 60000`.

$coroutine = Coroutine::create(function () {
    try {
        $client = (yield LocalClient::connect('google.com', 443, 'tcp', ['cn' => '*.google.com']));
        
        echo "Connected.\n";
        
        $time = (yield $client->enableCrypto());
        
        echo "Crypto enabled in {$time} seconds.\n";
        
        yield $client->write("GET / HTTP/1.1\r\n");
        yield $client->write("Host: google.com\r\n");
        yield $client->write("Connection: close\r\n");
        yield $client->write("\r\n");
        
        while ($client->isReadable()) {
            $data = (yield $client->read());
            echo $data;
        }
    } catch (ClosedException $e) {
        // Connection ended normally.
    } finally {
        $client->close();
    }
    
    echo "\n";
});

$coroutine->capture(function (Exception $exception) {
    echo "Error: {$exception->getMessage()}\n";
});

Loop::run();
