<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

use Icicle\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Coroutine\Coroutine;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Loop;

if (!function_exists(__NAMESPACE__ . '\from')) {
    /**
     * @param array|\Traversable|\Icicle\Observable\Observable $traversable
     *
     * @return \Icicle\Observable\Observable
     */
    function from($traversable): Observable
    {
        if ($traversable instanceof Observable) {
            return $traversable;
        }

        return new Emitter(function (callable $emit) use ($traversable): \Generator {
            if (!is_array($traversable) && !$traversable instanceof \Traversable) {
                throw new InvalidArgumentError('Must provide an array or instance of Traversable.');
            }

            foreach ($traversable as $value) {
                yield from $emit($value);
            }
        });
    }

    /**
     * @param \Icicle\Observable\Observable[] $observables
     *
     * @return \Icicle\Observable\Observable
     */
    function merge(array $observables): Observable
    {
        return new Emitter(function (callable $emit) use ($observables): \Generator {
            /** @var \Icicle\Observable\Observable[] $observables */
            $observables = array_map(__NAMESPACE__ . '\from', $observables);

            $callback = function ($value) use (&$emitting, $emit) {
                while (null !== $emitting) {
                    yield $emitting; // Prevents simultaneous calls to $emit.
                }

                $emitting = new Delayed();

                yield $emit($value);

                $emitting->resolve();
                $emitting = null;
            };

            /** @var \Icicle\Coroutine\Coroutine[] $coroutines */
            $coroutines = array_map(function (Observable $observable) use ($callback) {
                return new Coroutine($observable->each($callback));
            }, $observables);

            try {
                return yield Awaitable\all($coroutines);
            } catch (\Exception $exception) {
                foreach ($coroutines as $coroutine) {
                    $coroutine->cancel($exception);
                }
                throw $exception;
            }
        });
    }

    /**
     * Converts a function accepting a callback ($emitter) that invokes the callback when an event is emitted into an
     * observable that emits the arguments passed to the callback function each time the callback function would be
     * invoked.
     *
     * @param callable<(mixed ...$args)> $emitter Function accepting a callback that periodically emits events.
     * @param int $index Position of callback function in emitter function argument list.
     * @param mixed ...$args Other arguments to pass to emitter function.
     *
     * @return \Icicle\Observable\Observable
     */
    function observe(callable $emitter, int $index = 0, ...$args): Observable
    {
        return new Emitter(function (callable $emit) use ($emitter, $index, $args): \Generator {
            $queue = new \SplQueue();

            /** @var \Icicle\Awaitable\Delayed $delayed */
            $callback = function (...$args) use (&$delayed, $queue) {
                if (null !== $delayed) {
                    $delayed->resolve($args);
                    $delayed = null;
                } else {
                    $queue->push($args);
                }
            };

            if (count($args) < $index) {
                throw new InvalidArgumentError('Too few arguments given to function.');
            }

            array_splice($args, $index, 0, [$callback]);

            $emitter(...$args);

            while (true) {
                if ($queue->isEmpty()) {
                    $delayed = new Delayed();
                    yield from $emit($delayed);
                } else {
                    yield from $emit($queue->shift());
                }
            }
        });
    }

    /**
     * Returns an observable that emits a value every $interval seconds, up to $count times (or indefinitely if $count
     * is 0). The value emitted is an integer of the number of times the observable emitted a value.
     *
     * @param float|int $interval Time interval between emitted values in seconds.
     * @param int $count Use 0 to emit values indefinitely.
     *
     * @return \Icicle\Observable\Observable
     */
    function interval(float $interval, int $count = 0): Observable
    {
        return new Emitter(function (callable $emit) use ($interval, $count): \Generator {
            if (0 > $count) {
                throw new InvalidArgumentError('The number of times to emit must be a non-negative value.');
            }

            $start = microtime(true);

            $i = 0;
            $delayed = new Delayed();

            $timer = Loop\periodic($interval, function () use (&$delayed, &$i) {
                $delayed->resolve(++$i);
                $delayed = new Delayed();
            });

            try {
                while (0 === $count || $i < $count) {
                    yield from $emit($delayed);
                }
            } finally {
                $timer->stop();
            }

            return microtime(true) - $start;
        });
    }
}
