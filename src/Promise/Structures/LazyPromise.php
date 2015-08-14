<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise\Structures;

use Icicle\Promise;
use Icicle\Promise\{PromiseInterface, PromiseTrait};

class LazyPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var \Icicle\Promise\PromiseInterface|null
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
     * @return \Icicle\Promise\PromiseInterface
     */
    protected function getPromise(): PromiseInterface
    {
        if (null === $this->promise) {
            $promisor = $this->promisor;
            $this->promisor = null;
            
            try {
                $this->promise = Promise\resolve($promisor());
            } catch (\Throwable $exception) {
                $this->promise = Promise\reject($exception);
            }
        }
        
        return $this->promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface
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
    public function timeout(float $timeout, $reason = null): PromiseInterface
    {
        return $this->getPromise()->timeout($timeout, $reason);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay(float $time): PromiseInterface
    {
        return $this->getPromise()->delay($time);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->getPromise()->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled(): bool
    {
        return $this->getPromise()->isFulfilled();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
    {
        return $this->getPromise()->isRejected();
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled(): bool
    {
        return $this->getPromise()->isCancelled();
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->getPromise()->wait();
    }
    
    /**
     * {@inheritdoc}
     */
    public function unwrap(): PromiseInterface
    {
        return $this->getPromise()->unwrap();
    }
}
