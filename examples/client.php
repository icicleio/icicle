#!/usr/bin/php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\LocalClient;

// Connect using `nc localhost 60000`.

$coroutine = Coroutine::create(function () {
    $start = microtime(true);
    
    $client = (yield LocalClient::connect('localhost', 60000));
    
    $buffer = '';
    
    for ($i = 0; $i < 100000; ++$i) {
        $buffer .= 'a';
    }
    
    for ($i = 0; $i < 100; ++$i) {
        $client->write($buffer . "\n");
        $data = (yield $client->read());
        
        echo "{$i}) Got message of length: " . strlen($data) . "\n";
    }
    
    //yield $client->end("exit\n");
    
    for ($i = 0; $client->isReadable(); ++$i) {
        $data = (yield $client->read(1000, 1));
        
        echo "{$i}) Got message of length: " . strlen($data) . "\n";
    }
    
    yield microtime(true) - $start;
});

$coroutine->then(
    function ($value) {
        echo "Coroutine fulfilled with: {$value}\n";
    },
    function (Exception $exception) {
        echo "Coroutine rejected with: {$exception->getMessage()}\n";
    }
);

Loop::run();
