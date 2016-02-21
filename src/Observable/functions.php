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
    function from($values)
    {
        if ($values instanceof Observable) {
            return $values;
        }

        return new Emitter(function (callable $emit) use ($values) {
            if (is_array($values) || $values instanceof \Traversable) {
                foreach ($values as $value) {
                    yield $emit($value);
                }
            } else {
                yield $emit($values);
            }

            yield null; // Yield null so last emitted value is not the return value (not needed in PHP 7).
        });
    }

    /**
     * Returns an observable that immediately fails.
     *
     * @param \Exception $exception
     *
     * @return \Icicle\Observable\Observable
     */
    function fail(\Exception $exception)
    {
        return new Emitter(function (callable $emit) use ($exception) {
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
    function merge(array $observables)
    {
        /** @var \Icicle\Observable\Observable[] $observables */
        $observables = array_map(__NAMESPACE__ . '\from', $observables);

        return new Emitter(function (callable $emit) use ($observables) {
            /** @var \Icicle\Coroutine\Coroutine[] $coroutines */
            $coroutines = array_map(function (Observable $observable) use ($emit) {
                return new Coroutine($observable->each($emit));
            }, $observables);

            try {
                yield Awaitable\all($coroutines);
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
     * @param callable(mixed ...$args) $emitter Function accepting a callback that periodically emits events.
     * @param callable(callable $callback, \Exception $exception) $onDisposed Called if the observable is disposed.
     *     The callback passed to this function is the callable provided to the $emitter callable given to this
     *     function.
     * @param int $index Position of callback function in emitter function argument list.
     * @param mixed ...$args Other arguments to pass to emitter function.
     *
     * @return \Icicle\Observable\Observable
     */
    function observe(callable $emitter, callable $onDisposed = null, $index = 0 /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 3);

        $emitter = function (callable $emit) use (&$callback, $emitter, $index, $args) {
            $delayed = new Delayed();
            $reject = [$delayed, 'reject'];

            $callback = function () use ($emit, $reject) {
                $coroutine = new Coroutine($emit(func_get_args()));
                $coroutine->done(null, $reject);
            };

            if (count($args) < $index) {
                throw new InvalidArgumentError('Too few arguments given to function.');
            }

            array_splice($args, $index, 0, [$callback]);

            call_user_func_array($emitter, $args);

            yield $delayed;
        };

        if (null !== $onDisposed) {
            $onDisposed = function (\Exception $exception) use (&$callback, $onDisposed) {
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
    function concat(array $observables)
    {
        /** @var \Icicle\Observable\Observable[] $observables */
        $observables = array_map(__NAMESPACE__ . '\from', $observables);

        return new Emitter(function (callable $emit) use ($observables) {
            $results = [];

            foreach ($observables as $key => $observable) {
                $results[$key] = (yield $emit($observable));
            }

            yield $results;
        });
    }

    /**
     * @param \Icicle\Observable\Observable[] $observables
     *
     * @return \Icicle\Observable\Observable
     */
    function zip(array $observables)
    {
        /** @var \Icicle\Observable\Observable[] $observables */
        $observables = array_map(__NAMESPACE__ . '\from', $observables);

        return new Emitter(function (callable $emit) use ($observables) {
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
                            yield $emit($next);
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

                foreach ($coroutines as $coroutine) {
                    $coroutine->cancel();
                }
            } catch (\Exception $exception) {
                foreach ($coroutines as $coroutine) {
                    $coroutine->cancel($exception);
                }
                throw $exception;
            }

            yield $i; // Return the number of times a set was emitted.
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
    function interval($interval, $count = 0)
    {
        return new Emitter(function (callable $emit) use ($interval, $count) {
            $count = (int) $count;
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
                    yield $emit($delayed);
                }
            } finally {
                $timer->stop();
            }

            yield microtime(true) - $start;
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
    function of(/* ...$args */)
    {
        return from(func_get_args());
    }

    /**
     * @param int $start
     * @param int $end
     * @param int $step
     *
     * @return \Icicle\Observable\Emitter
     */
    function range($start, $end, $step = 1)
    {
        $start = (int) $start;
        $end = (int) $end;
        $step = (int) $step;

        return new Emitter(function (callable $emit) use ($start, $end, $step) {
            if (0 === $step) {
                throw new InvalidArgumentError('Step must be a non-zero integer.');
            }

            if ((($end - $start) ^ $step) < 0) {
                throw new InvalidArgumentError('Step is not of the correct sign.');
            }

            for ($i = $start; $i <= $end; $i += $step) {
                yield $emit($i);
            }

            yield null; // Yield null so last emitted value is not the return value (not needed in PHP 7).
        });
    }
}
