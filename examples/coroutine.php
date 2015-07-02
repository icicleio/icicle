#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

$generator = function () {
    try {
        // Sets $start to the value returned by microtime() after approx. 1 second.
        $start = (yield Promise\resolve(microtime(true))->delay(1));

        echo "Sleep time: ", microtime(true) - $start, "\n";

        // Throws the exception from the rejected promise into the coroutine.
        yield Promise\reject(new Exception('Rejected promise'));
    } catch (Exception $e) { // Catches promise rejection reason.
        echo "Caught exception: ", $e->getMessage(), "\n";
    }

    yield Promise\resolve('Coroutine completed');
};

$coroutine = new Coroutine($generator());

$coroutine->done(function ($data) {
    echo $data, "\n";
});

Loop\run();
