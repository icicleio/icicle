<?php
namespace Icicle\Timer;

interface TimerInterface
{
    /**
     * Gets the interval for this timer in seconds.
     *
     * @return  float
     *
     * @api
     */
    public function getInterval();
    
    /**
     * Determines if the timer will be repeated.
     *
     * @return  bool
     *
     * @api
     */
    public function isPeriodic();
    
    /**
     * Restarts the timer with the given parameters. Leave parameters null to keep previous values.
     *
     * @param   float|null $interval Number of seconds until invoking the callback, or null for no change.
     * @param   bool $periodic True to make the timer periodic, false for a one-time timer, or null for no change.
     *
     * @api
     */
    public function set($interval = null, $periodic = null);
    
    /**
     * Cancels this timer.
     *
     * @api
     */
    public function cancel();
    
    /**
     * Determines if the timer is pending and will be called again.
     *
     * @return  bool
     *
     * @api
     */
    public function isPending();
    
    /**
     * An unreferenced timer will allow the event loop to exit if no other events are pending.
     *
     * @api
     */
    public function unreference();
    
    /**
     * Adds a reference to the timer, causing the event loop to continue to run if the timer is still pending.
     *
     * @api
     */
    public function reference();
    
    /**
     * Alias of set().
     *
     * @param   float|null $interval
     * @param   bool|null $periodic
     *
     * @api
     */
    public function __invoke($interval = null, $periodic = null);
    
    /**
     * Executes the timer.
     */
    public function call();
}
