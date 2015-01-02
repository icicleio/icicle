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
        $client = (yield LocalClient::connect('www.google.com', 443));
        
        yield $client->enableCrypto();
        
        yield $client->write("GET / HTTP/1.0\r\n");
        yield $client->write("Host: www.google.com\r\n");
        yield $client->write("\r\n");
        
        while ($client->isReadable()) {
            $data = (yield $client->read());
            echo $data;
        }
    } catch (ClosedException $e) {
        // Connection ended normally.
    } finally {
        yield $client->close();
    }
});

$coroutine->capture(function (Exception $exception) {
    echo "Error: {$exception->getMessage()}\n";
});

Loop::run();
