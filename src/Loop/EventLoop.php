<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Event;
use EventBase;
use Icicle\Exception\UnsupportedError;
use Icicle\Loop\Manager\Event\EventSignalManager;
use Icicle\Loop\Manager\Event\EventSocketManager;
use Icicle\Loop\Manager\Event\EventTimerManager;

/**
 * Uses the event extension to poll sockets for I/O and create timers.
 */
class EventLoop extends AbstractLoop
{
    /**
     * @var \EventBase
     */
    private $base;

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param \EventBase|null $base Use null for an EventBase object to be automatically created.
     *
     * @throws \Icicle\Loop\Exception\UnsupportedError If the event extension is not loaded.
     */
    public function __construct(
        $enableSignals = true,
        EventBase $base = null
    ) {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('event')) {
            throw new UnsupportedError(__CLASS__ . ' requires the event extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->base = $base ?: new EventBase();

        parent::__construct($enableSignals);
    }

    /**
     * @return \EventBase
     *
     * @internal
     * @codeCoverageIgnore
     */
    public function getEventBase()
    {
        return $this->base;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        $flags = EventBase::LOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EventBase::LOOP_NONBLOCK;
        }
        
        $this->base->loop($flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * Calls reInit() on the EventBase object.
     */
    public function reInit()
    {
        $this->base->reInit();
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager()
    {
        return new EventSocketManager($this, Event::READ);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager()
    {
        return new EventSocketManager($this, Event::WRITE);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager()
    {
        return new EventTimerManager($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager()
    {
        return new EventSignalManager($this);
    }
}
