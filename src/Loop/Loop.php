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
        if (null !== self::$instance) {
            throw new InitializedException('The loop has already been initialized.');
        }
        
        self::$instance = $loop;
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
        if (null === self::$instance) {
            self::$instance = LoopFactory::create();
        }
        
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
     * @param   callable $callback
     * @param   mixed ...$args
     */
    public static function nextTick(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        static::getInstance()->schedule($callback, $args);
    }
    
    /**
     * Sets the maximum number of callbacks set with nextTick() that will be executed per tick.
     *
     * @param   int $depth
     *
     * @api
     */
    public static function maxScheduleDepth($depth)
    {
        static::getInstance()->maxScheduleDepth($depth);
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
     * @api
     */
    public static function run()
    {
        static::getInstance()->run();
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
}
