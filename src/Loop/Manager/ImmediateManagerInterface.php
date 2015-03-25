<?php
namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\ImmediateInterface;

interface ImmediateManagerInterface extends ManagerInterface
{
    /**
     * Creates an immediate object connected to the manager.
     *
     * @param   callable $callback
     * @param   array $args
     *
     * @return  ImmediateInterface
     */
    public function create(callable $callback, array $args = null);
    
    /**
     * Removes the immediate from the loop.
     *
     * @param   ImmediateInterface $timer
     */
    public function cancel(ImmediateInterface $immediate);
    
    /**
     * Determines if the immediate is active in the loop.
     *
     * @param   ImmediateInterface $timer
     *
     * @return  bool
     */
    public function isPending(ImmediateInterface $immediate);
    
    /**
     * Calls the next pending immediate. Returns true if an immediate was executed, false if not.
     *
     * @return  bool
     */
    public function tick();
}
