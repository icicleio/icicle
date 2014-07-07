<?php
namespace Icicle\Promise;

use Exception;

class UncancellablePromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var Promise
     */
    private $promise;
    
    /**
     * @param   PromiseInterface $promise
     */
    public function __construct(PromiseInterface $promise)
    {
        $this->promise = $promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        return $this->promise->then($onFulfilled, $onRejected)->uncancellable();
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->promise->done($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, Exception $exception = null)
    {
        return $this->promise->timeout($timeout, $exception)->uncancellable();
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this->promise->delay($time)->uncancellable();
    }
    
    /**
     * No-op since the promise cannot be cancelled.
     *
     * @param   Exception|null $exception
     */
    public function cancel(Exception $exception = null) {}
    
    /**
     * {@inheritdoc}
     */
    public function fork(callable $onCancelled = null)
    {
        return $this->promise->fork($onCancelled);
    }
    
    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->promise->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return $this->promise->isFulfilled();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->promise->isRejected();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->promise->getResult();
    }
}
