<?php
namespace Icicle\Loop;

use Icicle\EventEmitter\EventEmitterInterface;

interface LoopInterface extends EventEmitterInterface
{
    /**
     * Determines if the necessary components for the loop class are available.
     *
     * @return  bool
     */
    public static function enabled();
    
    /**
     * Executes a single tick, processing callbacks and handling any available I/O.
     *
     * @param   bool $blocking Determines if the tick should block and wait for I/O if no other tasks are scheduled.
     */
    public function tick($blocking = true);
    
    /**
     * Starts the event loop.
     *
     * @return  bool True if the loop was stopped, false if the loop exited because no events remained.
     */
    public function run();
    
    /**
     * Stops the event loop.
     */
    public function stop();
    
    /**
     * Determines if the event loop is running.
     *
     * @return  bool
     */
    public function isRunning();
    
    /**
     * Removes all events (I/O, timers, callbacks, signal handlers, etc.) from the loop.
     */
    public function clear();
    
    /**
     * Performs any reinitializing necessary after forking.
     */
    public function reInit();
    
    /**
     * Sets the maximum number of callbacks set with schedule() that will be executed per tick.
     *
     * @param   int|null $depth
     *
     * @return  int Current max depth if $depth = null or previous max depth otherwise.
     */
    public function maxScheduleDepth($depth = null);
    
    /**
     * Define a callback function to be run after all I/O has been handled in the current tick.
     * Callbacks are called in the order defined.
     *
     * @param   callable $callback
     * @param   array $args Array of arguments to be passed to the callback function.
     */
    public function schedule(callable $callback, array $args = null);
    
    /**
     * Creates an event object that can be used to listen for available data on the stream socket.
     *
     * @param   resource $resource
     * @param   callable $callback
     *
     * @return  SocketEventInterface
     */
    public function poll($resource, callable $callback);
    
    /**
     * Creates an event object that can be used to wait for the socket resource to be available for writing.
     *
     * @param   resource $resource
     * @param   callable $callback
     *
     * @return  SocketEventInterface
     */
    public function await($resource, callable $callback);
    
    /**
     * Creates a timer object connected to the loop.
     *
     * @param   callable $callback
     * @param   int|float $interval
     * @param   bool $periodic
     * @param   array $args
     *
     * @return  TimerInterface
     */
    public function timer(callable $callback, $interval, $periodic = false, array $args = null);
    
    /**
     * Creates an immediate object connected to the loop.
     *
     * @param   callable $callback
     * @param   array $args
     *
     * @return  ImmediateInterface
     */
    public function immediate(callable $callback, array $args = null);

    /**
     * Determines if signal handling is enabled.
     * *
     * @return bool
     */
    public function signalHandlingEnabled();
}
