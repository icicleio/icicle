<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

interface ImmediateInterface
{
    /**
     * @return bool
     */
    public function isPending();

    /**
     * Execute the immediate if not pending.
     */
    public function execute();

    /**
     * Cancels the immediate if pending.
     */
    public function cancel();

    /**
     * Calls the callback associated with the immediate.
     */
    public function call();
    
    /**
     * Alias of call().
     */
    public function __invoke();
}
