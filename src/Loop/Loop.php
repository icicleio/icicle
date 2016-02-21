<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

interface Loop
{
    /**
     * Executes a single tick, processing callbacks and handling any available I/O.
     *
     * @param bool $blocking Determines if the tick should block and wait for I/O if no other tasks are scheduled.
     */
    public function tick($blocking = true);
    
    /**
     * Starts the event loop. If a function is provided, that function is executed immediately after starting the event
     * loop.
     *
     * @param callable()|null $initialize
     *
     * @return bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws \Icicle\Loop\Exception\RunningError If the loop was already running.
     */
    public function run(callable $initialize = null);
    
    /**
     * Stops the event loop.
     */
    public function stop();
    
    /**
     * Determines if the event loop is running.
     *
     * @return bool
     */
    public function isRunning();
    
    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     */
    public function clear();

    /**
     * Determines if there are any pending events in the loop. Returns true if there are no pending events.
     *
     * @return bool
     */
    public function isEmpty();
    
    /**
     * Performs any reinitializing necessary after forking.
     */
    public function reInit();
    
    /**
     * Sets the maximum number of callbacks set with Loop::queue() that will be executed per tick.
     *
     * @param int|null $depth Maximum number of functions to execute each tick. Use 0 for unlimited. Use null to
     *     retrieve the current max depth.
     *
     * @return int Previous max depth.
     */
    public function maxQueueDepth($depth = null);
    
    /**
     * Queue a callback function to be run after all I/O has been handled in the current tick.
     * Callbacks are called in the order queued.
     *
     * @param callable(mixed ...$args) $callback
     * @param mixed[] $args Array of arguments to be passed to the callback function.
     */
    public function queue(callable $callback, array $args = []);
    
    /**
     * Creates an event object that can be used to listen for available data on the stream or socket resource.
     *
     * @param resource $resource
     * @param callable(resource $resource, bool $expired, Io $io) $callback
     * @param bool $persistent
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Io
     *
     * @throws \Icicle\Loop\Exception\ResourceBusyError If a poll was already created for the resource.
     */
    public function poll($resource, callable $callback, $persistent = false, $data = null);
    
    /**
     * Creates an event object that can be used to wait for the stream or socket resource to be available for writing.
     *
     * @param resource $resource
     * @param callable(resource $resource, bool $expired, Io $io) $callback
     * @param bool $persistent
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Io
     *
     * @throws \Icicle\Loop\Exception\ResourceBusyError If an await was already created for the resource.
     */
    public function await($resource, callable $callback, $persistent = false, $data = null);
    
    /**
     * Creates a timer object connected to the loop.
     *
     * @param int|float $interval
     * @param bool $periodic
     * @param callable(Timer $timer) $callback
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Timer
     */
    public function timer($interval, $periodic, callable $callback, $data = null);
    
    /**
     * Creates an immediate object connected to the loop.
     *
     * @param callable(Immediate $immediate) $callback
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Immediate
     */
    public function immediate(callable $callback, $data = null);

    /**
     * @param int $signo
     * @param callable(int $signo, Signal $signal) $callback
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Signal
     */
    public function signal($signo, callable $callback, $data = null);

    /**
     * Determines if signal handling is enabled.
     * *
     * @return bool
     */
    public function isSignalHandlingEnabled();
}
