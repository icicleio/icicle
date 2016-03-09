<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle;

use Icicle\Awaitable;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;

if (!function_exists(__NAMESPACE__ . '\execute')) {
    /**
     * @param callable $callback Callback function to execute. This function may return a Generator written as a
     *     coroutine or return an Awaitable. If the the awaitable or coroutine is rejected, the rejection reason will
     *     be thrown from this function.
     * @param mixed ...$args Arguments given to the provided callback function.
     *
     * @return bool Returns true if the loop was stopped and events still remain in the loop, false if the loop ran to
     *     completion (that is, the loop ran until no events remained).
     */
    function execute(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        return Loop\run(function () use ($callback, $args) {
            $result = call_user_func_array($callback, $args);

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            } else {
                $result = Awaitable\resolve($result);
            }

            $result->done();
        });
    }
}
