<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Manager\AbstractSignalManager;
use Icicle\Loop\SelectLoop;

class SignalManager extends AbstractSignalManager
{
    /**
     * @param \Icicle\Loop\SelectLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(SelectLoop $loop, EventFactoryInterface $factory)
    {
        parent::__construct($loop, $factory);

        $callback = $this->createSignalCallback();

        foreach ($this->getSignalList() as $signal) {
            pcntl_signal($signal, $callback);
        }
    }

    /**
     * Dispatch any signals that have arrived.
     *
     * @internal
     */
    public function tick()
    {
        pcntl_signal_dispatch();
    }
}
