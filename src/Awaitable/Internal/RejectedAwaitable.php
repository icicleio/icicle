<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable\Awaitable;
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
            $result = $onRejected($this->exception);
        } catch (Throwable $exception) {
            return new self($exception);
        }

        if (!$result instanceof Awaitable) {
            $result = new FulfilledAwaitable($result);
        }

        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $exception = $this->exception;

        if (null !== $onRejected) {
            try {
                $onRejected($exception);
                return;
            } catch (Throwable $exception) {
                // Code below will rethrow exception from loop.
            }
        }

        Loop\queue(function () use ($exception) {
            throw $exception; // Rethrow exception in uncatchable way.
        });
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
