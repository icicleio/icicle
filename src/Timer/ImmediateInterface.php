<?php
namespace Icicle\Timer;

interface ImmediateInterface
{
    /**
     * Sets the immediate to execute if it not already pending.
     *
     * @api
     */
    public function set();
    
    /**
     * Cancels the immediate.
     *
     * @api
     */
    public function cancel();
    
    /**
     * Determines if the immediate is still waiting to be executed.
     *
     * @return  bool
     *
     * @api
     */
    public function isPending();
    
    /**
     * Alias of set().
     *
     * @api
     */
    public function __invoke();
    
    /**
     * Executes the immediate callback.
     */
    public function call();
}
