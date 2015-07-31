<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\EventLoop;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Manager\TimerManagerInterface;

class TimerManager implements TimerManagerInterface
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
     * @var EventFactoryInterface
     */
    private $factory;
    
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
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(EventLoop $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        $this->base = $this->loop->getEventBase();
        
        $this->timers = new ObjectStorage();
        
        $this->callback = function ($resource, $what, TimerInterface $timer) {
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
    public function isEmpty()
    {
        return !$this->timers->count();
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
    public function start(TimerInterface $timer)
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
    public function stop(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->free();
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(TimerInterface $timer)
    {
        return isset($this->timers[$timer]) && $this->timers[$timer]->pending;
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
            $this->timers->getInfo()->free();
        }
        
        $this->timers = new ObjectStorage();
    }
}