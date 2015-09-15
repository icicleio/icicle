<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\SignalInterface;

interface SignalManagerInterface extends EventManagerInterface
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
     * Unreferences the given signal event, that is, if the signal is pending in the loop, the loop should not continue
     * running.
     *
     * @param \Icicle\Loop\Events\SignalInterface $signal
     */
    public function unreference(SignalInterface $signal);

    /**
     * References a signal if it was previously unreferenced. That is, if the timer is pending the loop will continue
     * running.
     *
     * @param \Icicle\Loop\Events\SignalInterface $signal
     */
    public function reference(SignalInterface $signal);
}
