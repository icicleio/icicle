<?php
namespace Icicle\Loop\Events\Manager;

interface ManagerInterface
{
    /**
     * Determines if any events are pending in the manager.
     *
     * @return  bool
     */
    public function isEmpty();
    
    /**
     * Clears all events from the manager.
     */
    public function clear();
}
