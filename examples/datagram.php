#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Datagram;

// Connect using `nc -u localhost 60000`.

$coroutine = Coroutine::call(function (Datagram $datagram) {
    echo "Echo datagram running on {$datagram->getAddress()}:{$datagram->getPort()}\n";
    
    try {
        while ($datagram->isOpen()) {
            list($address, $port, $data) = (yield $datagram->receive());
            $data = trim($data, "\n");
            yield $datagram->send($address, $port, "Echo: {$data}\n");
        }
    } catch (Exception $e) {
        echo "{$e->getMessage()}\n";
        $datagram->close();
    }
}, Datagram::create('localhost', 60000));

$coroutine->capture(
    function (Exception $exception) {
        echo "Coroutine rejected with: {$exception->getMessage()}\n";
    }
);

Loop::run();
