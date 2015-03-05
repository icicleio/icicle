<?php
namespace Icicle\Loop\Events;

interface ImmediateInterface extends EventInterface
{
    /**
     * If pending, cancel the immediate so it is never executed.
     *
     * @api
     */
    public function cancel();
    
    /**
     * Calls the callback associated with the timer.
     */
    public function call();
    
    /**
     * Alias of call().
     */
    public function __invoke();
}
