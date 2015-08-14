<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\{
    ImmediateManagerInterface,
    SignalManagerInterface,
    SocketManagerInterface,
    TimerManagerInterface
};

interface EventFactoryInterface
{
    /**
     * @param \Icicle\Loop\Manager\SocketManagerInterface $manager
     * @param resource $resource Socket resource.
     * @param callable $callback Callback function invoked when data is available on the socket.
     *
     * @return \Icicle\Loop\Events\SocketEventInterface
     */
    public function socket(SocketManagerInterface $manager, $resource, callable $callback): SocketEventInterface;
    
    /**
     * @param \Icicle\Loop\Manager\TimerManagerInterface $manager
     * @param int|float $interval Timer interval.
     * @param bool $periodic Set to true to repeat the timer every interval seconds, false for a one-time timer.
     * @param callable $callback Callback function invoked after the interval elapses.
     * @param mixed[] $args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Events\TimerInterface
     */
    public function timer(
        TimerManagerInterface $manager,
        float $interval,
        bool $periodic,
        callable $callback,
        array $args = []
    ): TimerInterface;
    
    /**
     * @param \Icicle\Loop\Manager\ImmediateManagerInterface $manager
     * @param callable $callback Callback function to be invoked.
     * @param mixed[] $args Arguments to pass to the callback function.
     *
     * @return \Icicle\Loop\Events\ImmediateInterface
     */
    public function immediate(
        ImmediateManagerInterface $manager,
        callable $callback,
        array $args = []
    ): ImmediateInterface;

    /**
     * @param \Icicle\Loop\Manager\SignalManagerInterface $manager
     * @param int $signo
     * @param callable $callback
     *
     * @return \Icicle\Loop\Events\SignalInterface
     */
    public function signal(SignalManagerInterface $manager, int $signo, callable $callback): SignalInterface;
}