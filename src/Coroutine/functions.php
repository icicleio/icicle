<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine;

use Icicle\Awaitable;
use Icicle\Coroutine\Exception\InvalidCallableError;
use Icicle\Exception\UnexpectedTypeError;

if (!function_exists(__NAMESPACE__ . '\wrap')) {
    /**
     * Wraps the function returning a \Generator in a callable function that returns a new coroutine each time the
     * function is called.
     *
     * @param callable $worker
     *
     * @return callable
     */
    function wrap(callable $worker): callable
    {
        /**
         * @param mixed ...$args
         *
         * @return \Icicle\Coroutine\Coroutine
         */
        return function (...$args) use ($worker): Coroutine {
            return create($worker, ...$args);
        };
    }

    /**
     * Calls the function (which should return a Generator written as a coroutine), then runs the coroutine. This
     * function should not be called within a running event loop. This function is meant to be used to create an
     * initial coroutine that runs the rest of the application. The resolution value of the coroutine is returned or
     * the rejection reason is thrown from this function.
     *
     * @param callable $worker Function returning a Generator written as a coroutine.
     * @param mixed ...$args Arguments passed to $worker.
     *
     * @return mixed Coroutine resolution value.
     *
     * @throws \Exception Coroutine rejection reason.
     */
    function run(callable $worker, ...$args)
    {
        return create($worker, ...$args)->wait();
    }

    /**
     * Calls the callable with the given arguments which must return a \Generator, which is then made into a coroutine
     * and returned.
     *
     * @param callable $worker Function returning a Generator written as a coroutine.
     * @param mixed ...$args Arguments passed to $worker.
     *
     * @return \Icicle\Coroutine\Coroutine
     */
    function create(callable $worker, ...$args): Coroutine
    {
        try {
            $generator = $worker(...$args);

            if (!$generator instanceof \Generator) {
                throw new UnexpectedTypeError('Generator', $generator);
            }

            return new Coroutine($generator);
        } catch (\Throwable $exception) {
            return create(function () use ($exception, $worker) {
                throw new InvalidCallableError($worker, $exception);
                yield; // Unreachable, but makes the function a coroutine.
            });
        }
    }

    /**
     * @coroutine
     *
     * @param float $time Time to sleep in seconds.
     *
     * @return \Generator
     *
     * @resolve float Actual time slept in seconds.
     */
    function sleep(float $time): \Generator
    {
        $start = yield Awaitable\resolve(microtime(true))->delay($time);

        return microtime(true) - $start;
    }
}
