<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise\Internal;

use Exception;
use Icicle\Promise\Exception\CancelledException;

class CancelledPromise extends ResolvedPromise
{
    /**
     * @var \Icicle\Promise\PromiseInterface
     */
    private $result;

    /**
     * @param mixed $reason
     * @param callable|null $onCancelled
     */
    public function __construct($reason, callable $onCancelled = null)
    {
        if (!$reason instanceof Exception) {
            $reason = new CancelledException($reason);
        }

        $this->result = new RejectedPromise($reason);

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
        return $this->result->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return $this->result->isFulfilled();
    }

    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->result->isRejected();
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled()
    {
        return !$this->result->isPending();
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->result->wait();
    }
}
