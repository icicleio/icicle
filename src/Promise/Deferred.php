<?php
namespace Icicle\Promise;

class Deferred implements PromisorInterface
{
    /**
     * @var Promise
     */
    private $promise;
    
    /**
     * @var callable
     */
    private $resolve;
    
    /**
     * @var callable
     */
    private $reject;
    
    /**
     * @param callable|null $onCancelled
     */
    public function __construct(callable $onCancelled = null)
    {
        $this->promise = new Promise(function (callable $resolve, callable $reject) {
            $this->resolve = $resolve;
            $this->reject = $reject;
        }, $onCancelled);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getPromise(): PromiseInterface
    {
        return $this->promise;
    }
    
    /**
     * Fulfill the promise with the given value.
     *
     * @param mixed $value
     */
    public function resolve($value = null)
    {
        $resolve = $this->resolve;
        $resolve($value);
    }
    
    /**
     * Reject the promise the the given reason.
     *
     * @param mixed $reason
     */
    public function reject($reason = null)
    {
        $reject = $this->reject;
        $reject($reason);
    }
}
