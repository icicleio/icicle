<?php
namespace Icicle\Loop\Events;

interface EventInterface
{
    /**
     * @return  bool
     *
     * @api
     */
    public function isPending();

    /**
     * Cancel the event if pending.
     *
     * @api
     */
    public function cancel();

    /**
     * @return  callable
     */
    public function getCallback();
}
