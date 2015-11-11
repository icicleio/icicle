<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise;

interface Promisor
{
    /**
     * Returns the internal Thenable object.
     *
     * @return \Icicle\Promise\Thenable
     */
    public function getPromise();
}
