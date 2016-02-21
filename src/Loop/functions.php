<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

if (!function_exists(__NAMESPACE__ . '\loop')) {
    /**
     * Returns the default event loop. Can be used to set the default event loop if an instance is provided.
     *
     * @param \Icicle\Loop\Loop|null $loop
     * 
     * @return \Icicle\Loop\Loop
     */
    function loop(Loop $loop = null)
    {
        static $instance;

        if (null !== $loop) {
            $instance = $loop;
        } elseif (null === $instance) {
            $instance = create();
        }

        return $instance;
    }

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     *
     * @return \Icicle\Loop\Loop
     *
     * @codeCoverageIgnore
     */
    function create($enableSignals = true)
    {
        if (EvLoop::enabled()) {
            return new EvLoop($enableSignals);
        }

        return new SelectLoop($enableSignals);
    }

    /**
     * Runs the tasks set up in the given function in a separate event loop from the default event loop. If the default
     * is running, the default event loop is blocked while the separate event loop is running.
     *
     * @param callable $worker
     * @param Loop|null $loop
     *
     * @return bool
     */
    function with(callable $worker, Loop $loop = null)
    {
        $previous = loop();

        try {
            return loop($loop ?: create())->run($worker);
        } finally {
            loop($previous);
        }
    }
    
    /**
     * Queues a function to be executed later. The function may be executed as soon as immediately after
     * the calling scope exits. Functions are guaranteed to be executed in the order queued.
     *
     * @param callable(mixed ...$args) $callback
     * @param mixed ...$args
     */
    function queue(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        loop()->queue($callback, $args);
    }

    /**
     * Sets the maximum number of callbacks set with queue() that will be executed per tick.
     *
     * @param int|null $depth Maximum number of functions to execute each tick. Use 0 for unlimited. Use null to
     *     retrieve the current max depth.
     *
     * @return int Previous max depth.
     */
    function maxQueueDepth($depth = null)
    {
        return loop()->maxQueueDepth($depth);
    }

    /**
     * Executes a single tick of the event loop.
     *
     * @param bool $blocking
     */
    function tick($blocking = false)
    {
        loop()->tick($blocking);
    }

    /**
     * Starts the default event loop. If a function is provided, that function is executed immediately after starting
     * the event loop, passing the event loop as the first argument.
     *
     * @param callable(Loop $loop): void|null $initialize
     *
     * @return bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws \Icicle\Loop\Exception\RunningError If the loop was already running.
     */
    function run(callable $initialize = null)
    {
        return loop()->run($initialize);
    }

    /**
     * Determines if the event loop is running.
     *
     * @return bool
     */
    function isRunning()
    {
        return loop()->isRunning();
    }

    /**
     * Stops the event loop.
     */
    function stop()
    {
        loop()->stop();
    }

    /**
     * Determines if there are any pending events in the loop. Returns true if there are no pending events.
     *
     * @return bool
     */
    function isEmpty()
    {
        return loop()->isEmpty();
    }

    /**
     * @param resource $socket Stream socket resource.
     * @param callable(resource $resource, bool $expired, Io $io) $callback Callback to be invoked when data is
     *     available on the socket.
     * @param bool $persistent
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Io
     */
    function poll($socket, callable $callback, $persistent = false, $data = null)
    {
        return loop()->poll($socket, $callback, $persistent, $data);
    }

    /**
     * @param resource $socket Stream socket resource.
     * @param callable(resource $resource, bool $expired, Io $io) $callback Callback to be invoked when the socket is
     *     available to write.
     * @param bool $persistent
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Io
     */
    function await($socket, callable $callback, $persistent = false, $data = null)
    {
        return loop()->await($socket, $callback, $persistent, $data);
    }

    /**
     * @param float|int $interval Number of seconds before the callback is invoked.
     * @param callable(Timer $timer) $callback Function to invoke when the timer expires.
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Timer
     */
    function timer($interval, callable $callback, $data = null)
    {
        return loop()->timer($interval, false, $callback, $data);
    }

    /**
     * @param float|int $interval Number of seconds between invocations of the callback.
     * @param callable(Timer $timer) $callback Function to invoke when the timer expires.
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Timer
     */
    function periodic($interval, callable $callback, $data = null)
    {
        return loop()->timer($interval, true, $callback, $data);
    }

    /**
     * @param callable $callback Function to invoke when no other active events are available.
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Immediate
     */
    function immediate(callable $callback, $data = null)
    {
        return loop()->immediate($callback, $data);
    }

    /**
     * @param int $signo Signal number. (Use constants such as SIGTERM, SIGCONT, etc.)
     * @param callable(int $signo, Signal $signal) $callback Function to invoke when the given signal arrives.
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Signal
     */
    function signal($signo, callable $callback, $data = null)
    {
        return loop()->signal($signo, $callback, $data);
    }

    /**
     * Determines if signal handling is enabled.
     *
     * @return bool
     */
    function isSignalHandlingEnabled()
    {
        return loop()->isSignalHandlingEnabled();
    }

    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     */
    function clear()
    {
        loop()->clear();
    }

    /**
     * Performs any reinitializing necessary after forking.
     */
    function reInit()
    {
        loop()->reInit();
    }
}
