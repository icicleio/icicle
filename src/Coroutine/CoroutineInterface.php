<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine;

use Icicle\Promise\PromiseInterface;

interface CoroutineInterface extends PromiseInterface
{
    /**
     * Pauses the coroutine.
     */
    public function pause();
    
    /**
     * Resumes the coroutine if it was paused.
     */
    public function resume();
    
    /**
     * @return bool
     */
    public function isPaused(): bool;
}
