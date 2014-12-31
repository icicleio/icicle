<?php
namespace Icicle\Promise\Structures;

use Exception;
use Icicle\Promise\PromiseInterface;
use Icicle\Promise\PromiseTrait;

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
}
