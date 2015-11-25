<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\{EventLoop, Structures\ObjectStorage, Manager\TimerManager, Watcher\Timer};

class EventTimerManager implements TimerManager
{
    /**
     * @var \Icicle\Loop\EventLoop
     */
    private $loop;

    /**
     * @var EventBase
     */
    private $base;

    /**
     * ObjectStorage mapping Timer objects to Event objects.
     *
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $timers;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param \Icicle\Loop\EventLoop $loop
     */
    public function __construct(EventLoop $loop)
    {
        $this->loop = $loop;
        $this->base = $this->loop->getEventBase();
        
        $this->timers = new ObjectStorage();
        
        $this->callback = function ($resource, $what, Timer $timer) {
            if (!$this->timers[$timer]->pending) {
                $this->timers[$timer]->free();
                unset($this->timers[$timer]);
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
            $this->timers->getInfo()->free();
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
        $flags = Event::TIMEOUT;
        if ($timer->isPeriodic()) {
            $flags |= Event::PERSIST;
        }

        $event = new Event($this->base, -1, $flags, $this->callback, $timer);

        $this->timers[$timer] = $event;

        $event->add($timer->getInterval());
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop(Timer $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->free();
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Timer $timer): bool
    {
        return isset($this->timers[$timer]) && $this->timers[$timer]->pending;
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
            $this->timers->getInfo()->free();
        }
        
        $this->timers = new ObjectStorage();
    }
}