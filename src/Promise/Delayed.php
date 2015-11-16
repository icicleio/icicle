<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise;

/**
 * Awaitable implementation that should not be returned from a public API, but used only internally.
 */
class Delayed extends Future
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
    public function reject($reason = null)
    {
        parent::reject($reason);
    }
}
