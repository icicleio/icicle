<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

interface SignalInterface extends EventInterface
{
    /**
     * Calls the callback associated with the timer.
     */
    public function call();

    /**
     * Alias of call().
     */
    public function __invoke();

    /**
     * @param callable $callback
     */
    public function setCallback(callable $callback);

    /**
     * Enables listening for the signal.
     */
    public function enable();

    /**
     * Disables listening for the signal.
     */
    public function disable();

    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Signal identifier constant value, such as SIGTERM or SIGCHLD.
     *
     * @return int
     */
    public function getSignal(): int;
}
