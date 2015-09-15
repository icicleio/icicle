<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\UnsupportedError;
use Icicle\Loop\Manager\Libevent\SignalManager;
use Icicle\Loop\Manager\Libevent\SocketManager;
use Icicle\Loop\Manager\Libevent\TimerManager;

/**
 * Uses the libevent extension to poll sockets for I/O and create timers.
 */
class LibeventLoop extends AbstractLoop
{
    /**
     * Event base created with event_base_new().
     *
     * @var resource
     */
    private $base;

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     * @param resource|null Resource created by event_base_new() or null to automatically create an event base.
     *
     * @throws \Icicle\Loop\Exception\UnsupportedError If the libevent extension is not loaded.
     */
    public function __construct($enableSignals = true, EventFactoryInterface $eventFactory = null, $base = null)
    {
        // @codeCoverageIgnoreStart
        if (!extension_loaded('libevent')) {
            throw new UnsupportedError(__CLASS__ . ' requires the libevent extension.');
        } // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        if (!is_resource($base)) {
            $this->base = event_base_new();
        } else { // @codeCoverageIgnoreEnd
            $this->base = $base;
        }
        
        parent::__construct($enableSignals, $eventFactory);
    }

    /**
     * @return resource
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
    public function reInit()
    {
        event_base_reinit($this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        $flags = EVLOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EVLOOP_NONBLOCK;
        }
        
        event_base_loop($this->base, $flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory, EV_READ);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory, EV_WRITE);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return new TimerManager($this, $factory);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(EventFactoryInterface $factory)
    {
        return new SignalManager($this, $factory);
    }
}
