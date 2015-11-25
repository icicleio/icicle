<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\LibeventLoop;
use Icicle\Loop\Manager\TimerManager;
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Watcher\Timer;

class LibeventTimerManager implements TimerManager
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
     */
    public function __construct(LibeventLoop $loop)
    {
        $this->loop = $loop;
        $this->base = $this->loop->getEventBase();
        
        $this->timers = new ObjectStorage();
        
        $this->callback = function ($resource, $what, Timer $timer) {
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
    public function isEmpty()
    {
        return !$this->timers->count();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($interval, $periodic, callable $callback, array $args = [])
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
    public function stop(Timer $timer)
    {
        if (isset($this->timers[$timer])) {
            event_free($this->timers[$timer]);
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Timer $timer)
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
            event_free($this->timers->getInfo());
        }
        
        $this->timers = new ObjectStorage();
    }
}