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
        
        $request  = "GET / HTTP/1.1\r\n";
        $request .= "Host: google.com\r\n";
        $request .= "Connection: close\r\n";
        $request .= "\r\n";
        
        // Write request.
        yield $client->write($request);
        
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
