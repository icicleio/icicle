<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise\Internal;

use Icicle\Promise\{PromiseInterface, PromiseTrait};

abstract class ResolvedPromise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * {@inheritdoc}
     */
    public function cancel($reason = null) {}
    
    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled(): bool
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout(float $timeout, $reason = null): PromiseInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function delay(float $time): PromiseInterface
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap(): PromiseInterface
    {
        return $this;
    }
}
