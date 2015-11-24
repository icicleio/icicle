<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable\{Awaitable, function resolve, function reject};
use Icicle\Loop;
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

        try {
            return resolve($onRejected($this->exception));
        } catch (Throwable $exception) {
            return reject($exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onRejected) {
            try {
                $onRejected($this->exception);
            } catch (Throwable $exception) {
                Loop\queue(function () use ($exception) {
                    throw $exception;
                });
            }
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
