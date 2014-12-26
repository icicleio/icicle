#!/usr/bin/php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\StreamSocket\LocalClient;

// Connect using `nc localhost 60000`.

$coroutine = Coroutine::call(function (LocalClient $client) {
    
    yield $client->ready();
    
    $buffer = '';
    
    for ($i = 0; $i < 1000; ++$i) {
        $buffer .= 'a';
    }
    
    for ($i = 0; $i < 1000; ++$i) {
        /* yield */ $client->write($buffer . "\n");
        $data = (yield $client->read());
        
        echo "{$i}) Got message of length: " . strlen($data) . "\n";
    }
    
    yield $client->end("exit\n");
    
}, LocalClient::create('localhost', 60000));

$coroutine->then(
    function ($value) {
        echo "Coroutine fulfilled with: {$value}\n";
    },
    function (Exception $exception) {
        echo "Coroutine rejected with: {$exception->getMessage()}\n";
    }
);

Loop::run();
