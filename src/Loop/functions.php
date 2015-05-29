<?php
namespace Icicle\Loop;

use Icicle\Loop\Exception\InitializedException;

if (!function_exists(__NAMESPACE__ . '\loop')) {
    /**
     * Returns the global event loop.
     *
     * @param   \Icicle\Loop\LoopInterface|null $loop
     * 
     * @return  \Icicle\Loop\LoopInterface
     */
    function loop(LoopInterface $loop = null)
    {
        static $instance;

        // @codeCoverageIgnoreStart
        if (null !== $loop) {
            if (null !== $instance) {
                throw new InitializedException('The loop has already been initialized.');
            }
            $instance = $loop;
        } elseif (null === $instance) {
            $instance = create();
        } // @codeCoverageIgnoreEnd

        return $instance;
    }

    /**
     * @return  \Icicle\Loop\LoopInterface
     *
     * @codeCoverageIgnore
     */
    function create()
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
     * Schedules a function to be executed later. The function may be executed as soon as immediately after
     * the calling scope exits. Functions are guaranteed to be executed in the order queued.
     *
     * @param   callable $callback
     * @param   mixed ...$args
     */
    function schedule(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        loop()->schedule($callback, $args);
    }
    
    /**
     * Sets the maximum number of callbacks set with nextTick() that will be executed per tick.
     *
     * @param   int|null $depth
     *
     * @return  int Current max depth if $depth = null or previous max depth otherwise.
     */
    function maxScheduleDepth($depth = null)
    {
        return loop()->maxScheduleDepth($depth);
    }
    
    /**
     * Executes a single tick of the event loop.
     *
     * @param   bool $blocking
     */
    function tick($blocking = false)
    {
        loop()->tick($blocking);
    }
    
    /**
     * Runs the event loop, dispatching I/O events, timers, etc.
     *
     * @return  bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws  \Icicle\Loop\Exception\RunningException If the loop was already running.
     */
    function run()
    {
        return loop()->run();
    }
    
    /**
     * Determines if the event loop is running.
     *
     * @return  bool
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
     * @return  bool
     */
    function isEmpty()
    {
        return loop()->isEmpty();
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     * @param   callable $callback Callback to be invoked when data is available on the socket.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    function poll($socket, callable $callback)
    {
        return loop()->poll($socket, $callback);
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     * @param   callable $callback Callback to be invoked when the socket is available to write.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    function await($socket, callable $callback)
    {
        return loop()->await($socket, $callback);
    }
    
    /**
     * @param   float|int $interval Number of seconds before the callback is invoked.
     * @param   callable $callback Function to invoke when the timer expires.
     * @param   mixed ...$args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    function timer($interval, callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);

        return loop()->timer($interval, false, $callback, $args);
    }
    
    /**
     * @param   float|int $interval Number of seconds between invocations of the callback.
     * @param   callable $callback Function to invoke when the timer expires.
     * @param   mixed ...$args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    function periodic($interval, callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);

        return loop()->timer($interval, true, $callback, $args);
    }
    
    /**
     * @param   callable $callback Function to invoke when no other active events are available.
     * @param   mixed ...$args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\ImmediateInterface
     */
    function immediate(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        return loop()->immediate($callback, $args);
    }

    /**
     * @param   int $signo Signal number. (Use constants such as SIGTERM, SIGCONT, etc.)
     * @param   callable $callback Function to invoke when the given signal arrives.
     *
     * @return  \Icicle\Loop\Events\SignalInterface
     */
    function signal($signo, callable $callback)
    {
        return loop()->signal($signo, $callback);
    }
    
    /**
     * Determines if signal handling is enabled.
     *
     * @return  bool
     */
    function signalHandlingEnabled()
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
