<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Exception;
use Icicle\Awaitable\Exception\CancelledException;

class CancelledAwaitable extends ResolvedAwaitable
{
    /**
     * @var \Icicle\Awaitable\Awaitable
     */
    private $result;

    /**
     * @param mixed $reason
     * @param callable|null $onCancelled
     */
    public function __construct(\Exception $reason = null, callable $onCancelled = null)
    {
        if (!$reason instanceof Exception) {
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
    public function then(callable $onFulfilled = null, callable $onRejected = null)
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
    public function isPending()
    {
        return $this->result->isPending(); // Pending until cancellation function is invoked.
    }

    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->result->isRejected(); // Rejected once cancellation function is invoked.
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled()
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
