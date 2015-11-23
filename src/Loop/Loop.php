<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Icicle\Loop\Events\{Immediate, Signal, SocketEvent, Timer};

interface Loop
{
    /**
     * Executes a single tick, processing callbacks and handling any available I/O.
     *
     * @param bool $blocking Determines if the tick should block and wait for I/O if no other tasks are scheduled.
     */
    public function tick(bool $blocking = true);
    
    /**
     * Starts the event loop. If a function is provided, that function is executed immediately after starting the event
     * loop.
     *
     * @param callable<(): void>|null $initialize
     *
     * @return bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws \Icicle\Loop\Exception\RunningError If the loop was already running.
     */
    public function run(callable $initialize = null): bool;
    
    /**
     * Stops the event loop.
     */
    public function stop();
    
    /**
     * Determines if the event loop is running.
     *
     * @return bool
     */
    public function isRunning(): bool;
    
    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     */
    public function clear();

    /**
     * Determines if there are any pending events in the loop. Returns true if there are no pending events.
     *
     * @return bool
     */
    public function isEmpty(): bool;
    
    /**
     * Performs any reinitializing necessary after forking.
     */
    public function reInit();
    
    /**
     * Sets the maximum number of callbacks set with Loop::queue() that will be executed per tick.
     *
     * @param int $depth Maximum number of functions to execute each tick. Use 0 for unlimited.
     *
     * @return int Previous max depth.
     */
    public function maxQueueDepth(int $depth): int;
    
    /**
     * Queue a callback function to be run after all I/O has been handled in the current tick.
     * Callbacks are called in the order queued.
     *
     * @param callable<(mixed ...$args): void> $callback
     * @param mixed[] $args Array of arguments to be passed to the callback function.
     */
    public function queue(callable $callback, array $args = []);
    
    /**
     * Creates an event object that can be used to listen for available data on the stream or socket resource.
     *
     * @param resource $resource
     * @param callable<(resource $resource, bool $expired): void> $callback
     *
     * @return \Icicle\Loop\Events\Io
     *
     * @throws \Icicle\Loop\Exception\ResourceBusyError If a poll was already created for the resource.
     */
    public function poll($resource, callable $callback): SocketEvent;
    
    /**
     * Creates an event object that can be used to wait for the stream or socket resource to be available for writing.
     *
     * @param resource $resource
     * @param callable<(resource $resource, bool $expired): void> $callback
     *
     * @return \Icicle\Loop\Events\Io
     *
     * @throws \Icicle\Loop\Exception\ResourceBusyError If an await was already created for the resource.
     */
    public function await($resource, callable $callback): SocketEvent;
    
    /**
     * Creates a timer object connected to the loop.
     *
     * @param int|float $interval
     * @param bool $periodic
     * @param callable<(mixed ...$args): void> $callback
     * @param mixed[] $args
     *
     * @return \Icicle\Loop\Events\Timer
     */
    public function timer(float $interval, bool $periodic, callable $callback, array $args = []): Timer;
    
    /**
     * Creates an immediate object connected to the loop.
     *
     * @param callable<(mixed ...$args): void> $callback
     * @param mixed[] $args
     *
     * @return \Icicle\Loop\Events\Immediate
     */
    public function immediate(callable $callback, array $args = []): Immediate;

    /**
     * @param int $signo
     * @param callable<(int $signo): void> $callback
     *
     * @return \Icicle\Loop\Events\Signal
     */
    public function signal(int $signo, callable $callback): Signal;

    /**
     * Determines if signal handling is enabled.
     * *
     * @return bool
     */
    public function signalHandlingEnabled(): bool;
}
