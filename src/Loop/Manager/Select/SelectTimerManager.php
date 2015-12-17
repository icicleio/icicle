<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Manager\TimerManager;
use Icicle\Loop\SelectLoop;
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Watcher\Timer;
use SplPriorityQueue;

class SelectTimerManager implements TimerManager
{
    /**
     * @var \Icicle\Loop\SelectLoop
     */
    private $loop;

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
     */
    public function __construct(SelectLoop $loop)
    {
        $this->loop = $loop;

        $this->queue = new SplPriorityQueue();
        $this->timers = new ObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($interval, $periodic, callable $callback, $data = null)
    {
        $timer = new Timer($this, $interval, $periodic, $callback, $data);
        
        $this->start($timer);
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Timer $timer)
    {
        return $this->timers->contains($timer);
    }

    /**
     * {@inheritdoc}
     */
    public function start(Timer $timer)
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
    public function stop(Timer $timer)
    {
        $this->timers->detach($timer);
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
