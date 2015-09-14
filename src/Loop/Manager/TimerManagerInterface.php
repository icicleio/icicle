<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\TimerInterface;

interface TimerManagerInterface extends EventManagerInterface
{
    /**
     * Creates a timer object connected to the manager.
     *
     * @param int|float $interval
     * @param bool $periodic
     * @param callable $callback
     * @param mixed[]|null $args
     *
     * @return \Icicle\Loop\Events\TimerInterface
     */
    public function create(float $interval, bool $periodic, callable $callback, array $args = []): TimerInterface;

    /**
     * Starts the given timer if it is not already pending.
     *
     * @param \Icicle\Loop\Events\TimerInterface $timer
     */
    public function start(TimerInterface $timer);

    /**
     * Cancels the given timer.
     *
     * @param \Icicle\Loop\Events\TimerInterface $timer
     */
    public function stop(TimerInterface $timer);
    
    /**
     * Determines if the timer is pending.
     *
     * @param \Icicle\Loop\Events\TimerInterface $timer
     *
     * @return bool
     */
    public function isPending(TimerInterface $timer): bool;
    
    /**
     * Unreferences the given timer, that is, if the timer is pending in the loop, the loop should not continue running.
     *
     * @param \Icicle\Loop\Events\TimerInterface $timer
     */
    public function unreference(TimerInterface $timer);
    
    /**
     * References a timer if it was previously unreferenced. That is, if the timer is pending the loop will continue
     * running.
     *
     * @param \Icicle\Loop\Events\TimerInterface $timer
     */
    public function reference(TimerInterface $timer);
}
