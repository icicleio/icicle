<?php
namespace Icicle\Loop\Events;

interface ImmediateInterface extends EventInterface
{
    /**
     * If pending, cancel the immediate so it is never executed.
     */
    public function cancel();
}
