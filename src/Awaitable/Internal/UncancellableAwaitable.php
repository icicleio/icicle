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
    public function cancel(\Exception $reason = null) {}

    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, callable $onTimeout = null)
    {
        return $this->awaitable->timeout($timeout, $onTimeout);
    }

    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
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
    public function delay($time)
    {
        return $this->awaitable->delay($time);
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
    public function wait()
    {
        return $this->awaitable->wait();
    }

    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->awaitable->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return $this->awaitable->isFulfilled();
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->awaitable->isRejected();
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled()
    {
        return $this->awaitable->isCancelled();
    }
}
