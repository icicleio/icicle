<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

interface WatcherManager
{
    /**
     * Determines if any referenced watchers are pending in the manager.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Clears all watchers from the manager.
     */
    public function clear();
}
