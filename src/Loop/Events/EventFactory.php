<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\ImmediateManagerInterface;
use Icicle\Loop\Manager\SignalManagerInterface;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;

/**
 * Default event factory implementation.
 */
class EventFactory implements EventFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function socket(SocketManagerInterface $manager, $resource, callable $callback)
    {
        return new SocketEvent($manager, $resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timer(
        TimerManagerInterface$manager,
        $interval,
        $periodic,
        callable $callback,
        array $args = []
    ) {
        return new Timer($manager, $interval, $periodic, $callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function immediate(ImmediateManagerInterface $manager, callable $callback, array $args = [])
    {
        return new Immediate($manager, $callback, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function signal(SignalManagerInterface $manager, $signo, callable $callback)
    {
        return new Signal($manager, $signo, $callback);
    }
}
