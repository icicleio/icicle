#!/usr/bin/env php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Awaitable;
use Icicle\Coroutine;
use Icicle\Loop;
use Icicle\Observable\Emitter;

$observable = new Emitter(function (callable $emit) {
    yield $emit(Awaitable\resolve(1)->delay(0.5)); // Emitted values may be regular values or awaitables. The
    yield $emit(Awaitable\resolve(2)->delay(1.5)); // awaitable is resolved and the fulfillment value emitted.
    yield $emit(Awaitable\resolve(3)->delay(1));   // Rejection will cause the observable to end with an error.
    yield $emit(Awaitable\resolve(4)->delay(1));
    yield $emit(5); // The values starting here will be emitted in 0.5 second intervals because the coroutine
    yield $emit(6); // consuming values below takes 0.5 seconds per iteration. This behavior occurs because
    yield $emit(7); // observables respect back-pressure from consumers, waiting to emit a value until all
    yield $emit(8); // consumers have finished processing (if desired, see the docs on using and avoiding
    yield $emit(9); // back-pressure).
    yield $emit(10);
});

$coroutine = Coroutine\create(function () use ($observable) {
    $iter = $observable->getIterator();
    while (yield $iter->wait()) {
        printf("Base observable: %d\n", $iter->getCurrent());
        yield Coroutine\sleep(0.5); // Artificial back-pressure on observable.
    }
});
$coroutine->done();

$observable = $observable
    ->filter(function ($value) {
        return $value % 2 === 0; // Only emit if $value is even.
    })
    ->map(function ($value) {
        return pow(2, $value); // Emit 2 ^ $value.
    });

$coroutine = Coroutine\create(function () use ($observable) {
    $iter = $observable->getIterator();
    while (yield $iter->wait()) {
        printf("Filtered and mapped observable: %d\n", $iter->getCurrent());
    }
});
$coroutine->done();

Loop\run();
