<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

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
        $this->promise = new Promise(function (callable $resolve, callable $reject) use ($onCancelled) {
            $this->resolve = $resolve;
            $this->reject = $reject;
            return $onCancelled;
        });
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
        ($this->resolve)($value);
    }
    
    /**
     * Reject the promise the the given reason.
     *
     * @param mixed $reason
     */
    public function reject($reason = null)
    {
        ($this->reject)($reason);
    }
}
