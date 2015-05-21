<?php
namespace Icicle\Loop\Events\Manager;

use Icicle\Loop\Events\TimerInterface;

interface TimerManagerInterface
{
    /**
     * Creates a timer object connected to the manager.
     *
     * @param   int|float $interval
     * @param   bool $periodic
     * @param   callable $callback
     * @param   mixed[] $args
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    public function create($interval, $periodic, callable $callback, array $args = null);

    /**
     * Starts the given timer if it is not already pending.
     *
     * @param   \Icicle\Loop\Events\TimerInterface $timer
     */
    public function start(TimerInterface $timer);

    /**
     * Cancels the given timer.
     *
     * @param   \Icicle\Loop\Events\TimerInterface $timer
     */
    public function stop(TimerInterface $timer);
    
    /**
     * Determines if the timer is pending.
     *
     * @param   \Icicle\Loop\Events\TimerInterface $timer
     *
     * @return  bool
     */
    public function isPending(TimerInterface $timer);
    
    /**
     * Unreferences the given timer, that is, if the timer is pending in the loop, the loop should not continue running.
     *
     * @param   \Icicle\Loop\Events\TimerInterface $timer
     */
    public function unreference(TimerInterface $timer);
    
    /**
     * References a timer if it was previously unreferenced. That is, if the timer is pending the loop will continue
     * running.
     *
     * @param   \Icicle\Loop\Events\TimerInterface $timer
     */
    public function reference(TimerInterface $timer);

    /**
     * Determines if any referenced timers are pending in the manager.
     *
     * @return  bool
     */
    public function isEmpty();

    /**
     * Clears all timers from the manager.
     */
    public function clear();
}
