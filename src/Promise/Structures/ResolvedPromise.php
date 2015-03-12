<?php
namespace Icicle\Promise\Structures;

use Exception;
use Icicle\Promise\PromiseInterface;
use Icicle\Promise\PromiseTrait;

abstract class ResolvedPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @inheritdoc
     */
    public function cancel($reason = null) {}
    
    /**
     * @inheritdoc
     */
    public function isPending()
    {
        return false;
    }
    
    /**
     * @inheritdoc
     */
    public function timeout($timeout, $reason = null)
    {
        return $this;
    }
}
