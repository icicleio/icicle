#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Awaitable\Promise;
use Icicle\Loop;

$promise = new Promise(function (callable $resolve, callable $reject) {
    Loop\timer(1, function () use ($resolve) {
        $resolve("Promise resolved");
    });
});

$start = microtime(true);

$promise
    ->then(function ($data) use ($start) {
        return sprintf("%s after %4f seconds.\n", $data, microtime(true) - $start);
    })
    ->done(function ($data) {
        echo $data;
    });

Loop\run();
