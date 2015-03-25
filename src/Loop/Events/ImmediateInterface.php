<?php
namespace Icicle\Loop\Events;

interface ImmediateInterface extends EventInterface
{
    /**
     * Calls the callback associated with the timer.
     */
    public function call();
    
    /**
     * Alias of call().
     */
    public function __invoke();
}
