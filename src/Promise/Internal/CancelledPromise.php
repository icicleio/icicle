<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise\Internal;

use Icicle\Promise\{Exception\CancelledException, PromiseInterface};
use Throwable;

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
        if (!$reason instanceof Throwable) {
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
    public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface
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
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->result->wait();
    }
}
