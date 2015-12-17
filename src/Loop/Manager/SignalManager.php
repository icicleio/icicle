<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Watcher\Signal;

interface SignalManager extends WatcherManager
{
    /**
     * Creates a signal event connected to the manager.
     *
     * @param int $signo
     * @param callable $callback
     * @param mixed $data Optional data to associate with the watcher.
     *
     * @return \Icicle\Loop\Watcher\Signal
     */
    public function create($signo, callable $callback, $data = null);

    /**
     * Enables listening for the signal.
     *
     * @param \Icicle\Loop\Watcher\Signal $signal
     */
    public function enable(Signal $signal);

    /**
     * Disables listening for the signal.
     *
     * @param \Icicle\Loop\Watcher\Signal
     */
    public function disable(Signal $signal);
    
    /**
     * Determines if the signal event is in the loop.
     *
     * @param \Icicle\Loop\Watcher\Signal
     *
     * @return bool
     */
    public function isEnabled(Signal $signal);

    /**
     * Unreferences the given signal event, that is, if the signal is pending in the loop, the loop should not continue
     * running.
     *
     * @param \Icicle\Loop\Watcher\Signal $signal
     */
    public function unreference(Signal $signal);

    /**
     * References a signal if it was previously unreferenced. That is, if the timer is pending the loop will continue
     * running.
     *
     * @param \Icicle\Loop\Watcher\Signal $signal
     */
    public function reference(Signal $signal);
}
