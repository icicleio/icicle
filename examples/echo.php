<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Socket\Server;
use Icicle\Socket\Client;

$coroutine = Coroutine::call(function () {
    $server = Server::create('localhost', 8080);
    
    $handler = Coroutine::async(function (Client $client) {
        try {
            yield $client->pipe($client);
        } finally {
            $client->close();
        }
    });
    
    while ($server->isOpen()) {
        $handler(yield $server->accept());
    }
});

Loop::run();
