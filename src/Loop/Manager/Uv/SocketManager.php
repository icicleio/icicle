<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\Events\{EventFactoryInterface, SocketEventInterface};
use Icicle\Loop\Exception\{FreedError, ResourceBusyError, UvException};
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\UvLoop;

class SocketManager implements SocketManagerInterface
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
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $unreferenced = [];

    /**
     * @var callable Callback for poll events.
     */
    private $pollCallback;

    /**
     * @var callable Callback for timeout events.
     */
    private $timerCallback;

    /**
     * @var int
     */
    private $type;

    /**
     * @param \Icicle\Loop\UvLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param int $eventType
     */
    public function __construct(UvLoop $loop, EventFactoryInterface $factory, int $eventType)
    {
        $this->loopHandle = $loop->getLoopHandle();
        $this->factory = $factory;
        $this->type = $eventType;

        $this->pollCallback = function ($poll, int $status, int $events, $resource) {
            switch ($status) {
                // If $status is a severe error, stop the poll and throw an exception.
                case \UV::EACCES:
                case \UV::EBADF:
                case \UV::EINVAL:
                case \UV::ENOTSOCK:
                    \uv_poll_stop($poll);
                    throw new UvException($status);

                case 0: // OK
                    break;

                default: // Ignore other (probably) trivial warnings. Hopefully nothing goes wrong...
                    return;
            }

            \uv_poll_stop($poll);

            $id = (int) $resource;

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
        foreach ($this->polls as $id =>$poll) {
            if (\uv_is_active($poll) && !isset($this->unreferenced[$id])) {
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
            $this->polls[$id] = \uv_poll_init_socket($this->loopHandle, $resource);
        }

        // Begin polling for events.
        \uv_poll_start($this->polls[$id], $this->type, $this->pollCallback);

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
            unset($this->sockets[$id], $this->unreferenced[$id]);

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
    public function reference(SocketEventInterface $socket)
    {
        unset($this->unreferenced[(int) $socket->getResource()]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();

        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            $this->unreferenced[$id] = $socket;
        }
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
