<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

interface EventManagerInterface
{
    /**
     * Determines if any referenced events are pending in the manager.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Clears all events from the manager.
     */
    public function clear();
}
