<?php
namespace Icicle\Promise\Structures;

use Icicle\Loop;
use Icicle\Promise\Exception\RejectedException;
use Icicle\Promise\{Promise, PromiseInterface};
use Throwable;

class RejectedPromise extends ResolvedPromise
{
    /**
     * @var \Throwable
     */
    private $exception;
    
    /**
     * @param mixed $reason
     */
    public function __construct($reason)
    {
        if (!$reason instanceof Throwable) {
            $reason = new RejectedException($reason);
        }
        
        $this->exception = $reason;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface
    {
        if (null === $onRejected) {
            return $this;
        }
        
        return new Promise(function (callable $resolve, callable $reject) use ($onRejected) {
            Loop\queue(function () use ($resolve, $reject, $onRejected) {
                try {
                    $resolve($onRejected($this->exception));
                } catch (Throwable $exception) {
                    $reject($exception);
                }
            });
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onRejected) {
            Loop\queue($onRejected, $this->exception);
        } else {
            Loop\queue(function () {
                throw $this->exception; // Rethrow exception in uncatchable way.
            });
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay(float $time): PromiseInterface
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled(): bool
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->exception;
    }
}
