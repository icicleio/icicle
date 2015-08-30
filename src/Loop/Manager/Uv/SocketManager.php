<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\Events\{EventFactoryInterface, SocketEventInterface};
use Icicle\Loop\Exception\{FreedError, ResourceBusyError, UvException};
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\UvLoop;

abstract class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;
    const MILLISEC_PER_SEC = 1e3;

    /**
     * @var resource A uv_loop handle.
     */
    private $loopHandle;

    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;

    /**
     * @var resource[] Map of sockets to uv_poll handles.
     */
    private $polls = [];

    /**
     * @var \Icicle\Loop\Events\TimerInterface[] Map of sockets to timers.
     */
    private $timers = [];

    /**
     * @var int[] Map of timer handles to sockets.
     */
    private $handles = [];

    /**
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $sockets = [];

    /**
     * @var callable Callback for poll events.
     */
    private $pollCallback;

    /**
     * @var callable Callback for timeout events.
     */
    private $timerCallback;

    /**
     * Starts a uv_poll handle to begin listening for I/O events.
     *
     * @param resource $pollHandle A uv_poll handle.
     * @param callable $callback
     */
    abstract protected function beginPoll($pollHandle, callable $callback);

    /**
     * @param \Icicle\Loop\UvLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(UvLoop $loop, EventFactoryInterface $factory)
    {
        $this->loopHandle = $loop->getLoopHandle();
        $this->factory = $factory;

        $this->pollCallback = function ($poll, int $status, int $events, $resource) {
            // If $status is EAGAIN, the callback was a false alarm.
            if ($status === \UV::EAGAIN) {
                return;
            }

            \uv_poll_stop($poll);

            $id = (int) $resource;

            // Some other error.
            if ($status < 0) {
                throw new UvException($status);
            }

            if (isset($this->timers[$id]) && \uv_is_active($this->timers[$id])) {
                \uv_timer_stop($this->timers[$id]);
            }

            $this->sockets[$id]->call(false);
        };

        $this->timerCallback = function ($timer) {
            $id = $this->handles[(int) $timer];

            if (\uv_is_active($this->polls[$id])) {
                \uv_poll_stop($this->polls[$id]);

                $this->sockets[$id]->call(true);
            }
        };
    }

    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->polls as $poll) {
            \uv_close($poll);
        }

        foreach ($this->timers as $timer) {
            \uv_close($timer);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        foreach ($this->polls as $poll) {
            if (\uv_is_active($poll)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function create($resource, callable $callback): SocketEventInterface
    {
        $id = (int) $resource;

        if (isset($this->sockets[$id])) {
            throw new ResourceBusyError('A socket event has already been created for that resource.');
        }

        return $this->sockets[$id] = $this->factory->socket($this, $resource, $callback);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(SocketEventInterface $socket, float $timeout = 0)
    {
        $resource = $socket->getResource();
        $id = (int) $resource;

        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedError('Socket event has been freed.');
        }

        // If no poll handle exists for the socket, create one now.
        if (!isset($this->polls[$id])) {
            $this->polls[$id] = \uv_poll_init($this->loopHandle, $resource);
        }

        // Begin polling for events.
        $this->beginPoll($this->polls[$id], $this->pollCallback);

        // If a timeout is given, set up a separate timer for cancelling the poll in the future.
        if ($timeout) {
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }

            if (!isset($this->timers[$id])) {
                $timer = \uv_timer_init($this->loopHandle);
                $this->handles[(int) $timer] = $id;
                $this->timers[$id] = $timer;
            }

            \uv_timer_start($this->timers[$id], $timeout * self::MILLISEC_PER_SEC, 0, $this->timerCallback);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();

        if (isset($this->sockets[$id], $this->polls[$id])
            && $socket === $this->sockets[$id]
            && \uv_is_active($this->polls[$id])
        ) {
            \uv_poll_stop($this->polls[$id]);

            if (isset($this->timers[$id]) && \uv_is_active($this->timers[$id])) {
                \uv_timer_stop($this->timers[$id]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(SocketEventInterface $socket): bool
    {
        $id = (int) $socket->getResource();

        return isset($this->sockets[$id], $this->polls[$id])
            && $socket === $this->sockets[$id]
            && \uv_is_active($this->polls[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function free(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();

        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->sockets[$id]);

            if (isset($this->polls[$id])) {
                \uv_close($this->polls[$id]);
                unset($this->polls[$id]);
            }

            if (isset($this->timers[$id])) {
                $timer = $this->timers[$id];
                unset($this->handles[(int) $timer]);

                \uv_close($this->timers[$id]);
                unset($this->timers[$id]);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isFreed(SocketEventInterface $socket): bool
    {
        $id = (int) $socket->getResource();

        return !isset($this->sockets[$id]) || $socket !== $this->sockets[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->polls as $poll) {
            \uv_close($poll);
        }

        foreach ($this->timers as $timer) {
            \uv_close($timer);
        }

        $this->polls = [];
        $this->timers = [];
        $this->sockets = [];
    }
}
