<?php
namespace Icicle\Timer;

interface ImmediateInterface
{
    /**
     * Sets the immediate to execute if it not already pending.
     */
    public function set();
    
    /**
     * Executes the immediate callback.
     */
    public function call();
    
    /**
     * Cancels the immediate.
     */
    public function cancel();
    
    /**
     * Determines if the immediate is still waiting to be executed.
     * @return  bool
     */
    public function isPending();
    
    /**
     * Alias of set().
     */
    public function __invoke();
}
