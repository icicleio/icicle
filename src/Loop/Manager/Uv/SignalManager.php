<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\UvLoop;
use Icicle\Loop\Manager\AbstractSignalManager;

class SignalManager extends AbstractSignalManager
{
    /**
     * @var resource[]
     */
    private $sigHandles = [];

    /**
     * @param \Icicle\Loop\UvLoop                       $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(UvLoop $loop, EventFactoryInterface $factory)
    {
        parent::__construct($loop, $factory);

        $loopHandle = $loop->getLoopHandle();

        $signalCallback = $this->createSignalCallback();
        $callback = function ($sigHandle, int $signo) use ($signalCallback) {
            $signalCallback($signo);
        };

        foreach ($this->getSignalList() as $signo) {
            $sigHandle = \uv_signal_init($loopHandle);

            \uv_signal_start($sigHandle, $callback, $signo);

            $this->sigHandles[$signo] = $sigHandle;
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->sigHandles as $sigHandle) {
            \uv_signal_stop($sigHandle);
        }
    }
}
