<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\{Manager\TimerManager, Structures\ObjectStorage, UvLoop, Watcher\Timer};

class UvTimerManager implements TimerManager
{
    const MILLISEC_PER_SEC = 1e3;

    /**
     * @var resource A uv_loop handle.
     */
    private $loopHandle;

    /**
     * @var \Icicle\Loop\Structures\ObjectStorage ObjectStorage mapping Timer objects to uv_timer handles.
     */
    private $timers;

    /**
     * @var \Icicle\Loop\Watcher\Timer[] Array mapping uv_timer handles to Timer objects.
     */
    private $handles = [];

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param \Icicle\Loop\UvLoop $loop
     */
    public function __construct(UvLoop $loop)
    {
        $this->loopHandle = $loop->getLoopHandle();

        $this->timers = new ObjectStorage();

        $this->callback = function ($handle) {
            $id = (int) $handle;

            if (!isset($this->handles[$id])) {
                return;
            }

            $timer = $this->handles[$id];

            if (!$timer->isPeriodic()) {
                \uv_close($this->timers[$timer]);
                unset($this->timers[$timer]);
                unset($this->handles[$id]);
            }

            $timer->call();
        };
    }

    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            \uv_close($this->timers->getInfo());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return !$this->timers->count();
    }

    /**
     * {@inheritdoc}
     */
    public function create(float $interval, bool $periodic, callable $callback, array $args = []): Timer
    {
        $timer = new Timer($this, $interval, $periodic, $callback, $args);

        $this->start($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Timer $timer)
    {
        if (!isset($this->timers[$timer])) {
            $handle = \uv_timer_init($this->loopHandle);

            $interval = $timer->getInterval() * self::MILLISEC_PER_SEC;

            \uv_timer_start(
                $handle,
                $interval,
                $timer->isPeriodic() ? $interval : 0,
                $this->callback
            );

            $this->timers[$timer] = $handle;
            $this->handles[(int) $handle] = $timer;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(Timer $timer)
    {
        if (isset($this->timers[$timer])) {
            $handle = $this->timers[$timer];

            \uv_close($handle);

            unset($this->timers[$timer]);
            unset($this->handles[(int) $handle]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isPending(Timer $timer): bool
    {
        return isset($this->timers[$timer]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(Timer $timer)
    {
        $this->timers->unreference($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function reference(Timer $timer)
    {
        $this->timers->reference($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            \uv_close($this->timers->getInfo());
        }

        $this->timers = new ObjectStorage();
        $this->handles = [];
    }
}
