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
     * Creates an observable instance from the given data. If the data is an array or Traversable, the observable will
     * emit each element of the array or Traversable. All other data types will result in an emitter that emits the
     * single value then completes with null. If any values are awaitables, the fulfillment value will be emitted or
     * the rejection reason will fail the returned observable.
     *
     * @param mixed $values
     *
     * @return \Icicle\Observable\Observable
     */
    function from($values): Observable
    {
        if ($values instanceof Observable) {
            return $values;
        }

        return new Emitter(function (callable $emit) use ($values): \Generator {
            if (is_array($values) || $values instanceof \Traversable) {
                foreach ($values as $value) {
                    yield from $emit($value);
                }
            } else {
                yield from $emit($values);
            }
        });
    }

    /**
     * Returns an observable that immediately fails.
     *
     * @param \Throwable $exception
     *
     * @return \Icicle\Observable\Observable
     */
    function fail(\Throwable $exception): Observable
    {
        return new Emitter(function (callable $emit) use ($exception): \Generator {
            throw $exception;
            yield; // Unreachable, but makes function a coroutine.
        });
    }

    /**
     * Creates an observable that emits values emitted from any observable in the array of observables. Values in the
     * array are passed through the from() function, so they may be observables, arrays of values to emit, awaitables,
     * or any other value.
     *
     * @param \Icicle\Observable\Observable[] $observables
     *
     * @return \Icicle\Observable\Observable
     */
    function merge(array $observables): Observable
    {
        /** @var \Icicle\Observable\Observable[] $observables */
        $observables = array_map(__NAMESPACE__ . '\from', $observables);

        return new Emitter(function (callable $emit) use ($observables): \Generator {
            /** @var \Icicle\Coroutine\Coroutine[] $coroutines */
            $coroutines = array_map(function (Observable $observable) use ($emit): Coroutine {
                return new Coroutine($observable->each($emit));
            }, $observables);

            try {
                yield Awaitable\all($coroutines);
            } catch (\Throwable $exception) {
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
     * @param callable(mixed ...$args) $emitter Function accepting a callback that periodically emits events.
     * @param callable(callable $callback, \Exception $exception) $onDisposed Called if the observable is disposed.
     *     The callback passed to this function is the callable provided to the $emitter callable given to this
     *     function.
     * @param int $index Position of callback function in emitter function argument list.
     * @param mixed ...$args Other arguments to pass to emitter function.
     *
     * @return \Icicle\Observable\Observable
     */
    function observe(callable $emitter, callable $onDisposed = null, int $index = 0, ...$args): Observable
    {
        $emitter = function (callable $emit) use (&$callback, $emitter, $index, $args): \Generator {
            $delayed = new Delayed();
            $reject = [$delayed, 'reject'];

            $callback = function (...$args) use ($emit, $reject) {
                $coroutine = new Coroutine($emit($args));
                $coroutine->done(null, $reject);
            };

            if (count($args) < $index) {
                throw new InvalidArgumentError('Too few arguments given to function.');
            }

            array_splice($args, $index, 0, [$callback]);

            $emitter(...$args);

            return yield $delayed;
        };

        if (null !== $onDisposed) {
            $onDisposed = function (\Throwable $exception) use (&$callback, $onDisposed) {
                $onDisposed($callback, $exception);
            };
        }

        return new Emitter($emitter, $onDisposed);
    }

    /**
     * @param array $observables
     *
     * @return \Icicle\Observable\Observable
     */
    function concat(array $observables): Observable
    {
        /** @var \Icicle\Observable\Observable[] $observables */
        $observables = array_map(__NAMESPACE__ . '\from', $observables);

        return new Emitter(function (callable $emit) use ($observables): \Generator {
            $results = [];

            foreach ($observables as $key => $observable) {
                $results[$key] = yield from $emit($observable);
            }

            return $results;
        });
    }

    /**
     * @param \Icicle\Observable\Observable[] $observables
     *
     * @return \Icicle\Observable\Observable
     */
    function zip(array $observables): Observable
    {
        /** @var \Icicle\Observable\Observable[] $observables */
        $observables = array_map(__NAMESPACE__ . '\from', $observables);

        return new Emitter(function (callable $emit) use ($observables): \Generator {
            $coroutines = [];
            $next = [];
            $delayed = new Delayed();
            $count = count($observables);

            $i = 0;
            foreach ($observables as $key => $observable) {
                $coroutines[$key] = new Coroutine($observable->each(
                    function ($value) use (&$i, &$next, &$delayed, $key, $count, $emit) {
                        if (isset($next[$key])) {
                            yield $delayed; // Wait for $next to be emitted.
                        }

                        $next[$key] = $value;

                        if (count($next) === $count) {
                            ++$i;
                            yield from $emit($next);
                            $next = [];
                            $temp = $delayed;
                            $delayed = new Delayed();
                            $temp->resolve();
                        }
                    }
                ));
            }

            try {
                yield Awaitable\choose($coroutines);
                yield $delayed;
            } finally {
                foreach ($coroutines as $coroutine) {
                    $coroutine->cancel();
                }
            }

            return $i; // Return the number of times a set was emitted.
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

    /**
     * Creates an observable of the arguments passed to this function. For example, of(1, 2, 3) will return an
     * observable that will emit the values 1, 2, and 3. Values can be awaitables.
     *
     * @param mixed ...$args
     *
     * @return \Icicle\Observable\Observable
     */
    function of(...$args): Observable
    {
        return from($args);
    }

    /**
     * @param int $start
     * @param int $end
     * @param int $step
     *
     * @return \Icicle\Observable\Emitter
     */
    function range(int $start, int $end, int $step = 1): Observable
    {
        return new Emitter(function (callable $emit) use ($start, $end, $step): \Generator {
            if (0 === $step) {
                throw new InvalidArgumentError('Step must be a non-zero integer.');
            }

            if ((($end - $start) ^ $step) < 0) {
                throw new InvalidArgumentError('Step is not of the correct sign.');
            }

            for ($i = $start; $i <= $end; $i += $step) {
                yield from $emit($i);
            }
        });
    }
}
