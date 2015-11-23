<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Loop;
use Icicle\Awaitable\{Awaitable, Exception\RejectedException, Promise};
use Throwable;

class RejectedAwaitable extends ResolvedAwaitable
{
    /**
     * @var \Throwable
     */
    private $exception;
    
    /**
     * @param \Throwable $reason
     */
    public function __construct(\Throwable $reason)
    {
        $this->exception = $reason;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): Awaitable
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
    public function wait()
    {
        throw $this->exception;
    }
}
