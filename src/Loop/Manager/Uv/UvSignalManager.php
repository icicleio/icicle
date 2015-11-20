<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\{Manager\AbstractSignalManager, UvLoop};

class UvSignalManager extends AbstractSignalManager
{
    /**
     * @var resource[]
     */
    private $sigHandles = [];

    /**
     * @param \Icicle\Loop\UvLoop $loop
     */
    public function __construct(UvLoop $loop)
    {
        parent::__construct($loop);

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
