#!/usr/bin/php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Client;
use Icicle\Socket\Exception\ClosedException;

$coroutine = Coroutine::call(function () {
    try {
        $client = (yield Client::connect('google.com', 443, ['cn' => '*.google.com']));
        
        echo "Connected.\n";
        
        $time = microtime(true);
        
        yield $client->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        $time = microtime(true) - $time;
        
        echo "Crypto enabled in {$time} seconds.\n";
        
        // Write request.
        yield $client->write("GET / HTTP/1.1\r\n");
        yield $client->write("Host: google.com\r\n");
        yield $client->write("Connection: close\r\n");
        yield $client->write("\r\n");
        
        // Read response.
        while ($client->isReadable()) {
            $data = (yield $client->read());
            echo $data;
        }
    } catch (ClosedException $e) {
        // Closed normally, ignore exception.
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
    } finally {
        $client->close();
    }
    
    echo "\n";
});

Loop::run();
