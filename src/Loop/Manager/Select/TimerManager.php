<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;
use Icicle\Loop\SelectLoop;
use Icicle\Loop\Structures\ObjectStorage;
use SplPriorityQueue;

class TimerManager implements TimerManagerInterface
{
    /**
     * @var \Icicle\Loop\SelectLoop
     */
    private $loop;

    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var \SplPriorityQueue
     */
    private $queue;
    
    /**
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $timers;
    
    /**
     * @param \Icicle\Loop\SelectLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(SelectLoop $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        
        $this->queue = new SplPriorityQueue();
        $this->timers = new ObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($interval, $periodic, callable $callback, array $args = null)
    {
        $timer = $this->factory->timer($this, $interval, $periodic, $callback, $args);
        
        $this->start($timer);
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function start(TimerInterface $timer)
    {
        if (!$this->timers->contains($timer)) {
            $timeout = microtime(true) + $timer->getInterval();
            $this->queue->insert([$timer, $timeout], -$timeout);
            $this->timers[$timer] = $timeout;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop(TimerInterface $timer)
    {
        $this->timers->detach($timer);
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
    public function isEmpty()
    {
        return !$this->timers->count();
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = new SplPriorityQueue();
        $this->timers = new ObjectStorage();
    }
    
    /**
     * Calculates the time remaining until the top timer is ready. Returns null if no timers are in the queue, otherwise
     * a non-negative value is returned.
     *
     * @return int|float|null
     *
     * @internal
     */
    public function getInterval()
    {
        while (!$this->queue->isEmpty()) {
            list($timer, $timeout) = $this->queue->top();

            if (!$this->timers->contains($timer) || $timeout !== $this->timers[$timer]) {
                $this->queue->extract(); // Timer was removed from queue.
                continue;
            }

            $timeout -= microtime(true);

            if (0 > $timeout) {
                return 0;
            }

            return $timeout;
        }
        
        return null;
    }
    
    /**
     * Executes any pending timers. Returns the number of timers executed.
     *
     * @return int
     *
     * @internal
     */
    public function tick()
    {
        $count = 0;
        $time = microtime(true);
        
        while (!$this->queue->isEmpty()) {
            list($timer, $timeout) = $this->queue->top();

            if (!$this->timers->contains($timer) || $timeout !== $this->timers[$timer]) {
                $this->queue->extract(); // Timer was removed from queue.
                continue;
            }

            if ($this->timers[$timer] > $time) { // Timer at top of queue has not expired.
                return $count;
            }

            // Remove and execute timer. Replace timer if persistent.
            $this->queue->extract();

            if ($timer->isPeriodic()) {
                $timeout = $time + $timer->getInterval();
                $this->queue->insert([$timer, $timeout], -$timeout);
                $this->timers[$timer] = $timeout;
            } else {
                $this->timers->detach($timer);
            }

            // Execute the timer.
            $timer->call();

            ++$count;
        }
        
        return $count;
    }
}
