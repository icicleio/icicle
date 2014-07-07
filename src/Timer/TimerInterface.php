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
    public function start();
    
    /**
     * Cancels this timer.
     */
    public function cancel();
    
    /**
     * Determines if the timer is active and will be called again.
     * @return  bool
     */
    public function isActive();
    
    /**
     */
    public function unreference();
    
    /**
     */
    public function reference();
}
