<?php
namespace Icicle\Promise;

use Exception;

class LazyPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var callable
     */
    private $worker;
    
    /**
     * @var callable|null
     */
    private $onCancelled;
    
    /**
     * @var Promise
     */
    private $promise;
    
    /**
     * @param   LoopInterface $loop
     * @param   callable $worker
     */
    public function __construct(callable $worker, callable $onCancelled = null)
    {
        $this->worker = $worker;
        $this->onCancelled = $onCancelled;
    }
    
    /**
     * @return  Promise
     */
    protected function getPromise()
    {
        if (null === $this->promise) {
            $this->promise = new Promise($this->worker, $this->onCancelled);
        }
        
        return $this->promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        return $this->getPromise()->then($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->getPromise()->done($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $exception = null)
    {
        $this->getPromise()->cancel($exception);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, Exception $exception = null)
    {
        return $this->getPromise()->timeout($timeout, $exception);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this->getPromise()->delay($time);
    }
    
    /**
     * {@inheritdoc}
     */
    public function fork(callable $onCancelled = null)
    {
        return $this->getPromise()->fork($onCancelled);
    }
    
    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        return new UncancellablePromise($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->getPromise()->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return $this->getPromise()->isFulfilled();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->getPromise()->isRejected();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->getPromise()->getResult();
    }
}
