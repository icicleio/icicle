<?php
namespace Icicle\Loop;

use Icicle\Loop\Exception\InitializedException;

/**
 * Facade class for accessing a Icicle\Loop\LoopInterface instance. A specific instance of Icicle\Loop\LoopInterface
 * can be given using the init() method, or an instance is automatically generated based on available extensions.
 */
abstract class Loop
{
    /**
     * @var LoopInterface|null
     */
    private static $instance;
    
    /**
     * Used to set the loop to a custom class. This method should be one of the first calls in a script.
     *
     * @param   \Icicle\Loop\LoopInterface $loop
     *
     * @throws  \Icicle\Loop\Exception\InitializedException If another loop has been set or created.
     */
    public static function init(LoopInterface $loop)
    {
        // @codeCoverageIgnoreStart
        if (null !== self::$instance) {
            throw new InitializedException('The loop has already been initialized.');
        } // @codeCoverageIgnoreEnd
        
        self::$instance = $loop;
    }
    
    /**
     * @return  \Icicle\Loop\LoopInterface
     *
     * @codeCoverageIgnore
     */
    protected static function create()
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
     * Returns the global event loop.
     *
     * @return  \Icicle\Loop\LoopInterface
     */
    public static function getInstance()
    {
        // @codeCoverageIgnoreStart
        if (null === self::$instance) {
            self::$instance = static::create();
        } // @codeCoverageIgnoreEnd
        
        return self::$instance;
    }
    
    /**
     * Schedules a function to be executed later. The function may be executed as soon as immediately after
     * the calling scope exits. Functions are guaranteed to be executed in the order queued.
     *
     * @param   callable $callback
     * @param   mixed ...$args
     */
    public static function schedule(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        static::getInstance()->schedule($callback, $args);
    }
    
    /**
     * Sets the maximum number of callbacks set with nextTick() that will be executed per tick.
     *
     * @param   int|null $depth
     *
     * @return  int Current max depth if $depth = null or previous max depth otherwise.
     */
    public static function maxScheduleDepth($depth = null)
    {
        return static::getInstance()->maxScheduleDepth($depth);
    }
    
    /**
     * Executes a single tick of the event loop.
     *
     * @param   bool $blocking
     */
    public static function tick($blocking = false)
    {
        static::getInstance()->tick($blocking);
    }
    
    /**
     * Runs the event loop, dispatching I/O events, timers, etc.
     *
     * @return  bool True if the loop was stopped, false if the loop exited because no events remained.
     *
     * @throws  \Icicle\Loop\Exception\RunningException If the loop was already running.
     */
    public static function run()
    {
        return static::getInstance()->run();
    }
    
    /**
     * Determines if the event loop is running.
     *
     * @return  bool
     */
    public static function isRunning()
    {
        return static::getInstance()->isRunning();
    }
    
    /**
     * Stops the event loop.
     */
    public static function stop()
    {
        static::getInstance()->stop();
    }

    /**
     * Determines if there are any pending events in the loop. Returns true if there are no pending events.
     *
     * @return  bool
     */
    public static function isEmpty()
    {
        return static::getInstance()->isEmpty();
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     * @param   callable $callback Callback to be invoked when data is available on the socket.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    public static function poll($socket, callable $callback)
    {
        return static::getInstance()->poll($socket, $callback);
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     * @param   callable $callback Callback to be invoked when the socket is available to write.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    public static function await($socket, callable $callback)
    {
        return static::getInstance()->await($socket, $callback);
    }
    
    /**
     * @param   float|int $interval Number of seconds before the callback is invoked.
     * @param   callable $callback Function to invoke when the timer expires.
     * @param   mixed ...$args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    public static function timer($interval, callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);
        
        return static::getInstance()->timer($interval, false, $callback, $args);
    }
    
    /**
     * @param   float|int $interval Number of seconds between invocations of the callback.
     * @param   callable $callback Function to invoke when the timer expires.
     * @param   mixed ...$args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    public static function periodic($interval, callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);
        
        return static::getInstance()->timer($interval, true, $callback, $args);
    }
    
    /**
     * @param   callable $callback Function to invoke when no other active events are available.
     * @param   mixed ...$args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\ImmediateInterface
     */
    public static function immediate(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        return static::getInstance()->immediate($callback, $args);
    }

    /**
     * @param   int $signo Signal number. (Use constants such as SIGTERM, SIGCONT, etc.)
     * @param   callable $callback Function to invoke when the given signal arrives.
     *
     * @return  \Icicle\Loop\Events\SignalInterface
     */
    public static function signal($signo, callable $callback)
    {
        return static::getInstance()->signal($signo, $callback);
    }
    
    /**
     * Determines if signal handling is enabled.
     *
     * @return  bool
     */
    public static function signalHandlingEnabled()
    {
        return static::getInstance()->signalHandlingEnabled();
    }

    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     */
    public static function clear()
    {
        static::getInstance()->clear();
    }
    
    /**
     * Performs any reinitializing necessary after forking.
     */
    public static function reInit()
    {
        static::getInstance()->reInit();
    }
}
