<?php
namespace Icicle\Timer;

interface TimerInterface
{
    /**
     * Executes the timer.
     */
    public function call();
    
    /**
     * Gets the interval for this timer in seconds.
     * @return  float
     */
    public function getInterval();
    
    /**
     * Determines if the timer will be repeated.
     * @return  bool
     */
    public function isPeriodic();
    
    /**
     * Starts the timer if it is not currently active.
     */
    public function set($interval, $periodic = false);
    
    /**
     * Cancels this timer.
     */
    public function cancel();
    
    /**
     * Determines if the timer is pending and will be called again.
     *
     * @return  bool
     */
    public function isPending();
    
    /**
     */
    public function unreference();
    
    /**
     */
    public function reference();
    
    /**
     * Alias of set().
     */
    public function __invoke();
}
