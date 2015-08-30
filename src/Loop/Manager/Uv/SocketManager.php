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
use Icicle\Loop\UvLoop;
use Icicle\Loop\Manager\SocketManagerInterface;

abstract class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \Icicle\Loop\UvLoop
     */
    private $loop;

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
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $sockets = [];

    /**
     * @var bool[]
     */
    private $pending = [];

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
     * @param \Icicle\Loop\UvLoop                       $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(UvLoop $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;

        $this->pollCallback = function ($poll, int $status, int $events, $resource) {
            // If $status is EAGAIN, the callback was a false alarm.
            if ($status === \UV::EAGAIN) {
                return;
            }

            // Some other error.
            if ($status < 0) {
                throw new UvException($status);
            }

            $this->pending[(int) $resource] = false;

            $socket = $this->sockets[(int) $resource];
            $socket->call(false);
        };

        $this->timerCallback = function (SocketEventInterface $socket) {
            $id = (int) $socket->getResource();
            unset($this->pending[$id]);
            unset($this->timers[$id]);

            $socket->call(true);
        };
    }

    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->polls as $poll) {
            \uv_poll_stop($poll);
            \uv_close($poll);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        foreach ($this->pending as $pending) {
            if ($pending) {
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

        $this->sockets[$id] = $this->factory->socket($this, $resource, $callback);
        $this->pending[$id] = false;

        return $this->sockets[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function listen(SocketEventInterface $socket, float $timeout = 0)
    {
        $id = (int) $socket->getResource();

        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedError('Socket event has been freed.');
        }

        // If no poll handle exists for the socket, create one now.
        if (!isset($this->polls[$id])) {
            $this->polls[$id] = \uv_poll_init($this->loop->getLoopHandle(), $socket->getResource());
        }

        // Begin polling for events.
        $this->beginPoll($this->polls[$id], $this->pollCallback);
        $this->pending[$id] = true;

        // If a timeout is given, set up a separate timer for cancelling the poll in the future.
        if ($timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }

            $this->timers[$id] = $this->loop->timer($timeout, false, $this->timerCallback, [$socket]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();

        if (isset($this->sockets[$id], $this->polls[$id]) && $socket === $this->sockets[$id]) {
            \uv_poll_stop($this->polls[$id]);
            $this->pending[$id] = false;

            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(SocketEventInterface $socket): bool
    {
        $id = (int) $socket->getResource();

        return isset($this->sockets[$id], $this->pending[$id])
            && $socket === $this->sockets[$id]
            && $this->pending[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function free(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();

        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->sockets[$id]);
            unset($this->pending[$id]);

            if (isset($this->polls[$id])) {
                \uv_close($this->polls[$id]);
                unset($this->polls[$id]);
            }

            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
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
            $timer->stop();
        }

        $this->polls = [];
        $this->timers = [];
        $this->sockets = [];
        $this->pending = [];
    }
}
