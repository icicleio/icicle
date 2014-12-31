<?php
namespace Icicle\Loop;

use Icicle\Loop\Exception\InitializedException;

abstract class Loop
{
    /**
     * @var LoopInstance|null
     */
    private static $instance;
    
    /**
     * Used to set the loop to a custom class. This method should be one of the first calls in a script.
     *
     * @param   LoopInterface $loop
     *
     * @throws  InitializedException Thrown if another loop has been set or created.
     *
     * @api
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
     * @return  LoopInterface
     *
     * @codeCoverageIgnore
     */
    protected static function create()
    {
/*
        if (EventLoop::enabled()) {
            return new EventLoop();
        }
        
        if (LibeventLoop::enabled()) {
            return new LibeventLoop();
        }
*/
        
        return new SelectLoop();
    }
    
    /**
     * Returns the global event loop.
     *
     * @return  LoopInterface
     *
     * @api
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
     *
     * @api
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
     *
     * @api
     */
    public static function maxScheduleDepth($depth = null)
    {
        return static::getInstance()->maxScheduleDepth($depth);
    }
    
    /**
     * Executes a single tick of the event loop.
     *
     * @param   bool $blocking
     *
     * @api
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
     * @api
     */
    public static function run()
    {
        return static::getInstance()->run();
    }
    
    /**
     * Determines if the event loop is running.
     *
     * @return  bool
     *
     * @api
     */
    public static function isRunning()
    {
        return static::getInstance()->isRunning();
    }
    
    /**
     * Stops the event loop.
     *
     * @api
     */
    public static function stop()
    {
        static::getInstance()->stop();
    }
    
    /**
     * @return  Poll
     */
    public static function poll($socket, callable $callback)
    {
        return static::getInstance()->createPoll($socket, $callback);
    }
    
    /**
     * @return  Await
     */
    public static function await($socket, callable $callback)
    {
        return static::getInstance()->createAwait($socket, $callback);
    }
    
    /**
     * @return  Timer
     */
    public static function timer($interval, callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);
        
        return static::getInstance()->createTimer($callback, $interval, false, $args);
    }
    
    /**
     * @return  Timer
     */
    public static function periodic($interval, callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 2);
        
        return static::getInstance()->createTimer($callback, $interval, true, $args);
    }
    
    /**
     * @return  Immediate
     */
    public static function immediate(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        return static::getInstance()->createImmediate($callback, $args);
    }
    
    /**
     * @return  bool
     *
     * @api
     */
    public static function signalHandlingEnabled()
    {
        return static::getInstance()->signalHandlingEnabled();
    }
    
    /**
     * Adds a signal handler function for the given signal number.
     *
     * @param   int $signo Signal number. (Use constants such as SIGTERM, SIGCONT, etc.)
     * @param   callable $listener
     * @param   bool $once The handler will only be executed on the next signal received if this is true.
     *
     * @api
     */
    public static function addSignalHandler($signo, callable $listener, $once = false)
    {
        static::getInstance()->addListener($signo, $listener, $once);
    }
    
    /**
     * Removes a signal handler function for the given signal number.
     *
     * @param   int $signo
     * @param   callable $listener
     *
     * @api
     */
    public static function removeSignalHandler($signo, callable $listener)
    {
        static::getInstance()->removeListener($signo, $listener);
    }
    
    /**
     * Removes all signal handlers for the given signal number, or all signal handlers if no number is given.
     *
     * @param   int|null $signo
     *
     * @api
     */
    public static function removeAllSignalHandlers($signo = null)
    {
        static::getInstance()->removeAllListeners($signo);
    }
    
    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     *
     * @api
     */
    public static function clear()
    {
        static::getInstance()->clear();
    }
    
    /**
     * Performs any reinitializing necessary after forking.
     *
     * @api
     */
    public static function reInit()
    {
        static::getInstance()->reInit();
    }
    
    /**
     * Provides access to any other methods of the LoopInterface object.
     *
     * @param   string $name
     * @param   array $args
     *
     * @return  mixed
     */
/*
    public static function __callStatic($name, array $args)
    {
        $method = [self::getInstance(), $name];
        
        if (!is_callable($method)) {
            throw new LogicException();
        }
        
        return call_user_func_array($method, $args);
    }
*/
}
