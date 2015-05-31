#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Socket\Datagram\DatagramInterface;
use Icicle\Socket\Datagram\DatagramFactory;

// Connect using `nc -u localhost 60000`.

$datagram = (new DatagramFactory())->create('127.0.0.1', 60000);

$generator = function (DatagramInterface $datagram) {
    echo "Echo datagram running on {$datagram->getAddress()}:{$datagram->getPort()}\n";
    
    try {
        while ($datagram->isOpen()) {
            list($address, $port, $data) = (yield $datagram->receive());
            $data = trim($data, "\n");
            yield $datagram->send($address, $port, "Echo: {$data}\n");
        }
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $datagram->close();
    }
};

$coroutine = new Coroutine($generator($datagram));

Loop\run();
