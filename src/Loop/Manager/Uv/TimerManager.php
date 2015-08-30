<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Uv;

use Icicle\Loop\Events\{EventFactoryInterface, TimerInterface};
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Manager\TimerManagerInterface;
use Icicle\Loop\UvLoop;

class TimerManager implements TimerManagerInterface
{
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
     * @var \Icicle\Loop\Structures\ObjectStorage ObjectStorage mapping Timer objects to uv_timer handles.
     */
    private $timers;

    /**
     * @var \Icicle\Loop\Events\TimerInterface[] Array mapping uv_timer handles to Timer objects.
     */
    private $handles = [];

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param \Icicle\Loop\UvLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(UvLoop $loop, EventFactoryInterface $factory)
    {
        $this->loopHandle = $loop->getLoopHandle();
        $this->factory = $factory;

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
    public function create(float $interval, bool $periodic, callable $callback, array $args = []): TimerInterface
    {
        $timer = $this->factory->timer($this, $interval, $periodic, $callback, $args);

        $this->start($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function start(TimerInterface $timer)
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
    public function stop(TimerInterface $timer)
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
    public function isPending(TimerInterface $timer): bool
    {
        return isset($this->timers[$timer]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(TimerInterface $timer)
    {
        $this->timers->unreference($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function reference(TimerInterface $timer)
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
