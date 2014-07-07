<?php
namespace Icicle\Promise;

use Exception;

abstract class ResolvedPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $exception = null) {}
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, Exception $exception = null)
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function fork(callable $onCancelled = null)
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        return $this;
    }
}
