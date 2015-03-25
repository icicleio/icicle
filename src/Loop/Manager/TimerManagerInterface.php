<?php
namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\TimerInterface;

interface TimerManagerInterface extends ManagerInterface
{
    /**
     * Creates a timer object connected to the manager.
     *
     * @param   callable $callback
     * @param   int|float $interval
     * @param   bool $periodic
     * @param   mixed[] $args
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    public function create(callable $callback, $interval, $periodic = false, array $args = null);
    
    /**
     * Cancels the given timer.
     *
     * @param   \Icicle\Loop\Events\TimerInterface $timer
     */
    public function cancel(TimerInterface $timer);
    
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
}
