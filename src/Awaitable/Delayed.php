<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

/**
 * Awaitable implementation that should not be returned from a public API, but used only internally.
 */
final class Delayed extends Future
{
    /**
     * {@inheritdoc}
     */
    public function resolve($value = null)
    {
        parent::resolve($value);
    }

    /**
     * {@inheritdoc}
     */
    public function reject(\Exception $reason)
    {
        parent::reject($reason);
    }
}
