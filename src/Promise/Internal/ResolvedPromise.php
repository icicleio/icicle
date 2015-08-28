<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise\Internal;

use Icicle\Promise\PromiseInterface;
use Icicle\Promise\PromiseTrait;

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
    public function isPending()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, $reason = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function unwrap()
    {
        return $this;
    }
}
