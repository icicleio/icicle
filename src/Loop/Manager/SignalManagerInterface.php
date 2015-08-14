<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\SignalInterface;

interface SignalManagerInterface
{
    /**
     * Creates a signal event connected to the manager.
     *
     * @param int $signo
     * @param callable $callback
     * @param mixed[]|null $args
     *
     * @return \Icicle\Loop\Events\SignalInterface
     */
    public function create(int $signo, callable $callback, array $args = []): SignalInterface;

    /**
     * Enables listening for the signal.
     *
     * @param \Icicle\Loop\Events\SignalInterface $signal
     */
    public function enable(SignalInterface $signal);

    /**
     * Disables listening for the signal.
     *
     * @param \Icicle\Loop\Events\SignalInterface
     */
    public function disable(SignalInterface $signal);
    
    /**
     * Determines if the signal event is in the loop.
     *
     * @param \Icicle\Loop\Events\SignalInterface
     *
     * @return bool
     */
    public function isEnabled(SignalInterface $signal): bool;

    /**
     * Clears all signals from the manager.
     */
    public function clear();
}
