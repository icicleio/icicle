<?php
namespace Icicle\Promise;

use Exception;
use Icicle\Loop\Loop;

class RejectedPromise extends ResolvedPromise
{
    /**
     * @var Exception
     */
    private $exception;
    
    /**
     * @var bool
     */
    private $throwException = true;
    
    /**
     * @param   Exception $exception
     */
    public function __construct(Exception $exception)
    {
        $this->exception = $exception;
        
        /*
         * This bit of code waits until the next tick to rethrow the exception used to reject the
         * promise in an uncatchable way if then() or done() is not called on this rejected promise.
         */
        Loop::schedule(function () {
            if ($this->throwException) {
                throw $this->exception;
            }
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null === $onRejected) {
            return $this;
        }
        
        $this->throwException = false;
        
        return new Promise(function ($resolve, $reject) use ($onRejected) {
            Loop::schedule(function () use ($resolve, $reject, $onRejected) {
                try {
                    $resolve($onRejected($this->exception));
                } catch (Exception $exception) {
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
            $this->throwException = false;
            Loop::schedule($onRejected, $this->exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
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
