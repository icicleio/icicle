<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Icicle\Exception\UnsupportedError;
use Icicle\Loop\Manager\{SignalManager, SocketManager, TimerManager};
use Icicle\Loop\Manager\Uv\{UvSignalManager, UvSocketManager, UvTimerManager};

/**
 * Uses the UV extension to poll sockets for I/O and create timers.
 */
class UvLoop extends AbstractLoop
{
    /**
     * @var resource A uv_loop handle created with uv_loop_new().
     */
    private $loopHandle;

    /**
     * @return bool True if UvLoop can be used, false otherwise.
     */
    public static function enabled(): bool
    {
        return extension_loaded('uv');
    }

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param resource|null $loop Resource created by uv_loop_new() or null to create a new event loop.
     *
     * @throws \Icicle\Exception\UnsupportedError If the uv extension is not loaded.
     */
    public function __construct(bool $enableSignals = true, $loop = null)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('uv')) {
            throw new UnsupportedError(__CLASS__ . ' requires the UV extension.');
        } // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        if (!is_resource($loop)) {
            $this->loopHandle = \uv_loop_new();
        } else { // @codeCoverageIgnoreEnd
            $this->loopHandle = $loop;
        }

        parent::__construct($enableSignals);
    }

    /**
     * Gets the libuv loop handle.
     *
     * @return resource
     *
     * @internal
     * @codeCoverageIgnore
     */
    public function getLoopHandle()
    {
        return $this->loopHandle;
    }

    /**
     * {@inheritdoc}
     */
    public function reInit()
    {
        // libuv handles forks automatically.
    }

    /**
     * Destroys the UV loop handle when the loop is destroyed.
     */
    public function __destruct()
    {
        if (is_resource($this->loopHandle)) {
            \uv_loop_delete($this->loopHandle);
            $this->loopHandle = null;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function dispatch(bool $blocking)
    {
        \uv_run($this->loopHandle, $blocking ? \UV::RUN_ONCE : \UV::RUN_NOWAIT);
    }

    /**
     * {@inheritdoc}
     */
    protected function createPollManager(): SocketManager
    {
        return new UvSocketManager($this, \UV::READABLE);
    }

    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(): SocketManager
    {
        return new UvSocketManager($this, \UV::WRITABLE);
    }

    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(): TimerManager
    {
        return new UvTimerManager($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(): SignalManager
    {
        return new UvSignalManager($this);
    }
}
