<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\{ImmediateInterface, SignalInterface, SocketEventInterface, TimerInterface};
use Icicle\Loop\Exception\InitializedError;

if (!function_exists(__NAMESPACE__ . '\loop')) {
    /**
     * Returns the active event loop. Can be used to set the active event loop if the event loop has not been accessed.
     *
     * @param \Icicle\Loop\LoopInterface|null $loop
     * 
     * @return \Icicle\Loop\LoopInterface
     *
     * @throws \Icicle\Loop\Exception\InitializedError If the loop has already been initialized.
     */
    function loop(LoopInterface $loop = null): LoopInterface
    {
        static $instance;

        if (null === $instance) {
            $instance = $loop ?: create();
        } elseif (null !== $loop) {
            throw new InitializedError('The loop has already been initialized.');
        }

        return $instance;
    }

    /**
     * @return \Icicle\Loop\LoopInterface
     *
     * @codeCoverageIgnore
     */
    function create(): LoopInterface
    {
        if (EventLoop::enabled()) {
            return new EventLoop();
        }

        if (LibeventLoop::enabled()) {
            return new LibeventLoop();
        }

        return new SelectLoop();
    }
    
    /**
     * Queues a function to be executed later. The function may be executed as soon as immediately after
     * the calling scope exits. Functions are guaranteed to be executed in the order queued.
     *
     * @param callable $callback
     * @param mixed ...$args
     */
    function queue(callable $callback, ...$args)
    {
        loop()->queue($callback, $args);
    }

    /**
     * Sets the maximum number of callbacks set with queue() that will be executed per tick.
     *
     * @param int $depth Maximum number of functions to execute each tick. Use 0 for unlimited.
     *
     * @return int Previous max depth.
     */
    function maxQueueDepth(int $depth): int
    {
        return loop()->maxQueueDepth($depth);
    }

    /**
     * Executes a single tick of the event loop.
     *
     * @param bool $blocking
     */
    function tick(bool $blocking = false)
    {
        loop()->tick($blocking);
    }

    /**
     * Runs the event loop, dispatching I/O events, timers, etc.
     *
     * @return bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws \Icicle\Loop\Exception\RunningError If the loop was already running.
     */
    function run()
    {
        return loop()->run();
    }

    /**
     * Determines if the event loop is running.
     *
     * @return bool
     */
    function isRunning(): bool
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
    function isEmpty(): bool
    {
        return loop()->isEmpty();
    }

    /**
     * @param resource $socket Stream socket resource.
     * @param callable $callback Callback to be invoked when data is available on the socket.
     *
     * @return \Icicle\Loop\Events\SocketEventInterface
     */
    function poll($socket, callable $callback): SocketEventInterface
    {
        return loop()->poll($socket, $callback);
    }

    /**
     * @param resource $socket Stream socket resource.
     * @param callable $callback Callback to be invoked when the socket is available to write.
     *
     * @return \Icicle\Loop\Events\SocketEventInterface
     */
    function await($socket, callable $callback): SocketEventInterface
    {
        return loop()->await($socket, $callback);
    }

    /**
     * @param float|int $interval Number of seconds before the callback is invoked.
     * @param callable $callback Function to invoke when the timer expires.
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Events\TimerInterface
     */
    function timer(float $interval, callable $callback, ...$args): TimerInterface
    {
        return loop()->timer($interval, false, $callback, $args);
    }

    /**
     * @param float|int $interval Number of seconds between invocations of the callback.
     * @param callable $callback Function to invoke when the timer expires.
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Events\TimerInterface
     */
    function periodic(float $interval, callable $callback, ...$args): TimerInterface
    {
        return loop()->timer($interval, true, $callback, $args);
    }

    /**
     * @param callable $callback Function to invoke when no other active events are available.
     * @param mixed ...$args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Events\ImmediateInterface
     */
    function immediate(callable $callback, ...$args): ImmediateInterface
    {
        return loop()->immediate($callback, $args);
    }

    /**
     * @param int $signo Signal number. (Use constants such as SIGTERM, SIGCONT, etc.)
     * @param callable $callback Function to invoke when the given signal arrives.
     *
     * @return \Icicle\Loop\Events\SignalInterface
     */
    function signal(int $signo, callable $callback): SignalInterface
    {
        return loop()->signal($signo, $callback);
    }

    /**
     * Determines if signal handling is enabled.
     *
     * @return bool
     */
    function signalHandlingEnabled(): bool
    {
        return loop()->signalHandlingEnabled();
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
