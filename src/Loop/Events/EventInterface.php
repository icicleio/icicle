<?php
namespace Icicle\Loop\Events;

interface EventInterface
{
    /**
     * @return  bool
     */
    public function isPending();

    /**
     * Cancel the event if pending.
     */
    public function cancel();

    /**
     * @return  callable
     */
    public function getCallback();
}
