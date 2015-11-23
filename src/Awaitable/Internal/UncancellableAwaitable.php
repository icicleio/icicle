<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable\Awaitable;

class UncancellableAwaitable implements Awaitable
{
    use AwaitableMethods;

    /**
     * @var \Icicle\Awaitable\Awaitable
     */
    private $awaitable;

    /**
     * @param \Icicle\Awaitable\Awaitable $awaitable
     */
    public function __construct(Awaitable $awaitable)
    {
        $this->awaitable = $awaitable;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(\Throwable $reason = null) {}

    /**
     * {@inheritdoc}
     */
    public function timeout(float $timeout, callable $onTimeout = null): Awaitable
    {
        return $this->awaitable->timeout($timeout, $onTimeout);
    }

    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): Awaitable
    {
        return $this->awaitable->then($onFulfilled, $onRejected);
    }

    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->awaitable->done($onFulfilled, $onRejected);
    }

    /**
     * {@inheritdoc}
     */
    public function delay(float $time): Awaitable
    {
        return $this->awaitable->delay($time);
    }

    /**
     * {@inheritdoc}
     */
    public function uncancellable(): Awaitable
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->awaitable->wait();
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->awaitable->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function isFulfilled(): bool
    {
        return $this->awaitable->isFulfilled();
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected(): bool
    {
        return $this->awaitable->isRejected();
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled(): bool
    {
        return $this->awaitable->isCancelled();
    }
}
