<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable;

class LazyAwaitable implements Awaitable\Awaitable
{
    use AwaitableMethods;
    
    /**
     * @var \Icicle\Awaitable\Awaitable|null
     */
    private $promise;
    
    /**
     * @var callable|null
     */
    private $promisor;
    
    /**
     * @param callable $promisor
     */
    public function __construct(callable $promisor)
    {
        $this->promisor = $promisor;
    }
    
    /**
     * @return \Icicle\Awaitable\Awaitable
     */
    protected function getPromise()
    {
        if (null === $this->promise) {
            $promisor = $this->promisor;
            $this->promisor = null;
            
            try {
                $this->promise = Awaitable\resolve($promisor());
            } catch (\Exception $exception) {
                $this->promise = Awaitable\reject($exception);
            }
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
    public function cancel($reason = null)
    {
        $this->getPromise()->cancel($reason);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, $reason = null)
    {
        return $this->getPromise()->timeout($timeout, $reason);
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
    public function isCancelled()
    {
        return $this->getPromise()->isCancelled();
    }

    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        return new UncancellableAwaitable($this);
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->getPromise()->wait();
    }
}
