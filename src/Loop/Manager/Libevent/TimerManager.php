<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\Events\{EventFactoryInterface, TimerInterface};
use Icicle\Loop\LibeventLoop;
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Manager\TimerManagerInterface;

class TimerManager implements TimerManagerInterface
{
    const MICROSEC_PER_SEC = 1e6;

    /**
     * @var \Icicle\Loop\LibeventLoop
     */
    private $loop;

    /**
     * @var resource
     */
    private $base;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * ObjectStorage mapping Timer objects to event resources.
     *
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $timers;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param \Icicle\Loop\LibeventLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(LibeventLoop $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        $this->base = $this->loop->getEventBase();
        
        $this->timers = new ObjectStorage();
        
        $this->callback = function ($resource, $what, TimerInterface $timer) {
            if ($timer->isPeriodic()) {
                event_add($this->timers[$timer], $timer->getInterval() * self::MICROSEC_PER_SEC);
            } else {
                event_free($this->timers[$timer]);
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
            event_free($this->timers->getInfo());
        }
        
        // Need to completely destroy timer events before freeing base or an error is generated.
        $this->timers = null;
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
            $event = event_new();
            event_timer_set($event, $this->callback, $timer);
            event_base_set($event, $this->base);

            $this->timers[$timer] = $event;

            event_add($event, $timer->getInterval() * self::MICROSEC_PER_SEC);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            event_free($this->timers[$timer]);
            unset($this->timers[$timer]);
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
            event_free($this->timers->getInfo());
        }
        
        $this->timers = new ObjectStorage();
    }
}