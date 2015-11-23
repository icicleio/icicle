<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

final class Deferred
{
    /**
     * @var \Icicle\Awaitable\Promise
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
     * @return \Icicle\Awaitable\Promise
     */
    public function getPromise()
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
     * @param \Exception $reason
     */
    public function reject(\Exception $reason = null)
    {
        $reject = $this->reject;
        $reject($reason);
    }
}
