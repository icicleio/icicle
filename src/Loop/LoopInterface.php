<?php
namespace Icicle\Loop;

use Icicle\EventEmitter\EventEmitterInterface;
use Icicle\Loop\Events\Await;
use Icicle\Loop\Events\Immediate;
use Icicle\Loop\Events\Poll;
use Icicle\Loop\Events\Timer;

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
     * @return  Poll
     */
    public function createPoll($resource, callable $callback);
    
    /**
     * @param   Poll $poll
     * @param   float $timeout
     */
    public function addPoll(Poll $poll, $timeout);
    
    /**
     * Cancels the given poll operation.
     *
     * @param   Poll $poll
     */
    public function cancelPoll(Poll $poll);
    
    /**
     * Determines if the given socket is pending (listneing for data).
     *
     * @param   Poll $poll
     *
     * @return  bool
     */
    public function isPollPending(Poll $poll);
    
    /**
     * @param   Poll $poll
     */
    public function freePoll(Poll $poll);
    
    /**
     * Adds the socket or stream resource to the queue waiting to write.
     *
     * @param   resource $resource
     * @param   callable $callback
     *
     * @return  Await
     */
    public function createAwait($resource, callable $callback);
    
    /**
     * @param   Await $await
     */
    public function addAwait(Await $await);
    
    /**
     * Removes the resoruce from the queue waiting to write.
     *
     * @param   Await $await
     */
    public function cancelAwait(Await $await);
    
    /**
     * Determines if the resource is waiting to write.
     *
     * @param   WritableSocketInterface $socket
     *
     * @return  bool
     */
    public function isAwaitPending(Await $await);
    
    /**
     * @param   Await $await
     */
    public function freeAwait(Await $await);
    
    /**
     * Completely removes the socket from the loop (stops listening for data or writing data).
     *
     * @param   SocketInterface $socket
     */
    //public function removeSocket(SocketInterface $socket);
    
    /**
     * Adds the given timer to the loop.
     *
     * @param   TimerInterface $timer
     */
    public function createTimer($interval, $periodic, callable $callback, array $args = []);
    
    /**
     * Removes the timer from the loop.
     *
     * @param   TimerInterface $timer
     */
    public function cancelTimer(Timer $timer);
    
    /**
     * Determines if the timer is active in the loop.
     *
     * @param   TimerInterface $timer
     *
     * @return  bool
     */
    public function isTimerPending(Timer $timer);
    
    /**
     * Adds the given timer to the loop.
     *
     * @param   TimerInterface $timer
     */
    public function createImmediate(callable $callback, array $args = []);
    
    /**
     * Removes the timer from the loop.
     *
     * @param   TimerInterface $timer
     */
    public function cancelImmediate(Immediate $immediate);
    
    /**
     * Determines if the timer is active in the loop.
     *
     * @param   TimerInterface $timer
     *
     * @return  bool
     */
    public function isImmediatePending(Immediate $immediate);
}
