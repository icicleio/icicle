<?php
namespace Icicle\Loop;

use Icicle\EventEmitter\EventEmitterInterface;
use Icicle\Loop\Events\AwaitInterface;
use Icicle\Loop\Events\ImmediateInterface;
use Icicle\Loop\Events\PollInterface;
use Icicle\Loop\Events\TimerInterface;

interface LoopInterface extends EventEmitterInterface
{
    const MIN_TIMEOUT = 0.001;
    
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
    public function schedule(callable $callback, array $args = []);
    
    /**
     * Adds the socket or stream resource to the loop and begins listening for data.
     *
     * @param   resource $resource
     * @param   callable $callback
     *
     * @return  PollInterface
     */
    public function createPoll($resource, callable $callback);
    
    /**
     * @param   PollInterface $poll
     * @param   float $timeout
     */
    public function listenPoll(PollInterface $poll, $timeout);
    
    /**
     * Cancels the given poll operation.
     *
     * @param   PollInterface $poll
     */
    public function cancelPoll(PollInterface $poll);
    
    /**
     * Determines if the poll is pending (listneing for data).
     *
     * @param   PollInterface $poll
     *
     * @return  bool
     */
    public function isPollPending(PollInterface $poll);
    
    /**
     * @param   PollInterface $poll
     */
    public function freePoll(PollInterface $poll);
    
    /**
     * @param   PollInterface $poll
     *
     * @return  bool
     */
    public function isPollFreed(PollInterface $poll);
    
    /**
     * Creates an await object to be used to wait for the socket resource to be available for writing.
     *
     * @param   resource $resource
     * @param   callable $callback
     *
     * @return  AwaitInterface
     */
    public function createAwait($resource, callable $callback);
    
    /**
     * Adds the await to the to the queue waiting to write.
     *
     * @param   AwaitInterface $await
     */
    public function listenAwait(AwaitInterface $await);
    
    /**
     * Removes the await from the queue waiting to write.
     *
     * @param   AwaitInterface $await
     */
    public function cancelAwait(AwaitInterface $await);
    
    /**
     * Determines if the resource is waiting to write.
     *
     * @param   AwaitInterface $await
     *
     * @return  bool
     */
    public function isAwaitPending(AwaitInterface $await);
    
    /**
     * @param   AwaitInterface $await
     */
    public function freeAwait(AwaitInterface $await);
    
    /**
     * @param   AwaitInterface $await
     *
     * @return  bool
     */
    public function isAwaitFreed(AwaitInterface $await);
    
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
    public function createTimer(callable $callback, $interval, $periodic = false, array $args = []);
    
    /**
     * Removes the timer from the loop.
     *
     * @param   TimerInterface $timer
     */
    public function cancelTimer(TimerInterface $timer);
    
    /**
     * Determines if the timer is active in the loop.
     *
     * @param   TimerInterface $timer
     *
     * @return  bool
     */
    public function isTimerPending(TimerInterface $timer);
    
    /**
     * Creates an immediate object connected to the loop.
     *
     * @param   callable $callback
     * @param   array $args
     *
     * @return  ImmediateInterface
     */
    public function createImmediate(callable $callback, array $args = []);
    
    /**
     * Removes the immediate from the loop.
     *
     * @param   ImmediateInterface $timer
     */
    public function cancelImmediate(ImmediateInterface $immediate);
    
    /**
     * Determines if the immediate is active in the loop.
     *
     * @param   ImmediateInterface $timer
     *
     * @return  bool
     */
    public function isImmediatePending(ImmediateInterface $immediate);
}
