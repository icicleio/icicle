<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

interface TimerInterface extends EventInterface
{
    /**
     * @return bool
     */
    public function isPending(): bool;

    /**
     * Start the timer if not pending.
     */
    public function start();

    /**
     * Stops the timer if not pending.
     */
    public function stop();

    /**
     * Gets the interval for this timer in seconds.
     *
     * @return float
     */
    public function getInterval(): float;

    /**
     * Determines if the timer will be repeated.
     *
     * @return bool
     */
    public function isPeriodic(): bool;
    
    /**
     * Calls the callback associated with the timer.
     */
    public function call();

    /**
     * Alias of call().
     */
    public function __invoke();
}
