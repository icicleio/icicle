<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable\Awaitable;

abstract class ResolvedAwaitable implements Awaitable
{
    use AwaitableMethods;
    
    /**
     * {@inheritdoc}
     */
    public function cancel(\Throwable $reason = null) {}
    
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
    public function timeout(float $timeout, callable $onTimeout = null): Awaitable
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function delay(float $time): Awaitable
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function uncancellable(): Awaitable
    {
        return $this;
    }
}
