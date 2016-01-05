<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable\{Awaitable, Exception\CancelledException};
use Throwable;

class CancelledAwaitable extends ResolvedAwaitable
{
    /**
     * @var \Icicle\Awaitable\Awaitable
     */
    private $result;

    /**
     * @param \Throwable|null $reason
     * @param callable|null $onCancelled
     */
    public function __construct(\Throwable $reason = null, callable $onCancelled = null)
    {
        if (null === $reason) {
            $reason = new CancelledException();
        }

        $this->result = new RejectedAwaitable($reason);

        if (null !== $onCancelled) {
            $this->result = $this->result->cleanup(function () use ($onCancelled, $reason) {
                return $onCancelled($reason);
            });
        }
    }

    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): Awaitable
    {
        if (null === $onRejected) {
            return $this;
        }

        return $this->result->then(null, $onRejected);
    }

    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->result->done(null, $onRejected);
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->result->isPending(); // Pending until cancellation function is invoked.
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
        return $this->result->isRejected(); // Rejected once cancellation function is invoked.
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled(): bool
    {
        return $this->result->isRejected(); // Cancelled once cancellation function is invoked.
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->result->wait();
    }
}
