<?php
namespace Icicle\Loop\Events;

interface SignalInterface
{
    /**
     * Calls the callback associated with the timer.
     */
    public function call();
    
    /**
     * Alias of call().
     */
    public function __invoke();

    /**
     * Enables listening for the signal.
     */
    public function enable();

    /**
     * Disables listening for the signal.
     */
    public function disable();

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Signal identifier constant value, such as SIGTERM or SIGCHLD.
     *
     * @return int
     */
    public function getSignal(): int;
}
