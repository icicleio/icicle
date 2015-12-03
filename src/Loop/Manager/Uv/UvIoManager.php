<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\{Manager\IoManager, UvLoop, Watcher\Io};
use Icicle\Loop\Exception\{FreedError, ResourceBusyError, UvException};

class UvIoManager implements IoManager
{
    const MIN_TIMEOUT = 0.001;
    const MILLISEC_PER_SEC = 1e3;

    /**
     * @var resource A uv_loop handle.
     */
    private $loopHandle;

    /**
     * @var resource[] Map of descriptors to uv_poll handles.
     */
    private $polls = [];

    /**
     * @var resource[] Map of sockets to timers.
     */
    private $timers = [];

    /**
     * @var int[] Map of timer handles to sockets.
     */
    private $handles = [];

    /**
     * @var \Icicle\Loop\Watcher\Io[]
     */
    private $sockets = [];

    /**
     * @var \Icicle\Loop\Watcher\Io[]
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
     * @param int $eventType
     */
    public function __construct(UvLoop $loop, int $eventType)
    {
        $this->loopHandle = $loop->getLoopHandle();
        $this->type = $eventType;

        $this->pollCallback = function ($poll, int $status, int $events, $resource) {
            $id = (int) $resource;

            switch ($status) {
                case 0: // OK
                    break;

                // If $status is a severe error, stop the poll and throw an exception.
                case \UV::EACCES:
                case \UV::EBADF:
                case \UV::EINVAL:
                case \UV::ENOTSOCK:
                    \uv_poll_stop($poll);
                    if (isset($this->timers[$id]) && \uv_is_active($this->timers[$id])) {
                        \uv_timer_stop($this->timers[$id]);
                    }
                    throw new UvException($status);

                default: // Ignore other (probably) trivial warnings and continuing polling.
                    return;
            }

            if ($this->sockets[$id]->isPersistent()) {
                if (isset($this->timers[$id]) && \uv_is_active($this->timers[$id])) {
                    \uv_timer_again($this->timers[$id]);
                }
            } else {
                \uv_poll_stop($poll);
                if (isset($this->timers[$id]) && \uv_is_active($this->timers[$id])) {
                    \uv_timer_stop($this->timers[$id]);
                }
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
    public function create($resource, callable $callback, bool $persistent = false): Io
    {
        $id = (int) $resource;

        if (isset($this->sockets[$id])) {
            throw new ResourceBusyError();
        }

        return $this->sockets[$id] = new Io($this, $resource, $callback, $persistent);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(Io $io, float $timeout = 0)
    {
        $resource = $io->getResource();
        $id = (int) $resource;

        if (!isset($this->sockets[$id]) || $io !== $this->sockets[$id]) {
            throw new FreedError();
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
    public function cancel(Io $io)
    {
        $id = (int) $io->getResource();

        if (isset($this->sockets[$id], $this->polls[$id])
            && $io === $this->sockets[$id]
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
    public function isPending(Io $io): bool
    {
        $id = (int) $io->getResource();

        return isset($this->sockets[$id], $this->polls[$id])
            && $io === $this->sockets[$id]
            && \uv_is_active($this->polls[$id]);
    }

    /**
     * {@inheritdoc}
     */
    public function free(Io $io)
    {
        $id = (int) $io->getResource();

        if (isset($this->sockets[$id]) && $io === $this->sockets[$id]) {
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
    public function isFreed(Io $io): bool
    {
        $id = (int) $io->getResource();

        return !isset($this->sockets[$id]) || $io !== $this->sockets[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function reference(Io $io)
    {
        unset($this->unreferenced[(int) $io->getResource()]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(Io $io)
    {
        $id = (int) $io->getResource();

        if (isset($this->sockets[$id]) && $io === $this->sockets[$id]) {
            $this->unreferenced[$id] = $io;
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
