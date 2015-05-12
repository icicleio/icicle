<?php
namespace Icicle\Loop\Events;

interface TimerInterface extends EventInterface
{
    /**
     * Gets the interval for this timer in seconds.
     *
     * @return  float
     */
    public function getInterval();
    
    /**
     * Determines if the timer will be repeated.
     *
     * @return  bool
     */
    public function isPeriodic();
    
    /**
     * An unreferenced timer will allow the event loop to exit if no other events are pending.
     */
    public function unreference();
    
    /**
     * Adds a reference to the timer, causing the event loop to continue to run if the timer is still pending.
     */
    public function reference();

    /**
     * Calls the callback associated with the timer.
     */
    public function call();
    
    /**
     * Alias of call().
     */
    public function __invoke();
}
