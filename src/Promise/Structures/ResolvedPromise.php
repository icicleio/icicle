<?php
namespace Icicle\Promise\Structures;

use Icicle\Promise\{PromiseInterface, PromiseTrait};

abstract class ResolvedPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * {@inheritdoc}
     */
    public function cancel($reason = null) {}
    
    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout(float $timeout, $reason = null): PromiseInterface
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function unwrap(): PromiseInterface
    {
        return $this;
    }
}
