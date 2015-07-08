<?php
namespace Icicle\Loop\Events;

interface ImmediateInterface
{
    /**
     * @return bool
     */
    public function isPending(): bool;

    /**
     * Execute the immediate if not pending.
     */
    public function execute();

    /**
     * Cancels the immediate if pending.
     */
    public function cancel();

    /**
     * Calls the callback associated with the immediate.
     */
    public function call();
    
    /**
     * Alias of call().
     */
    public function __invoke();
}
