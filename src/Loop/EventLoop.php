<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Event;
use EventBase;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\UnsupportedError;
use Icicle\Loop\Manager\Event\SignalManager;
use Icicle\Loop\Manager\Event\SocketManager;
use Icicle\Loop\Manager\Event\TimerManager;

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
     * Determines if the event extension is loaded, which is required for this class.
     *
     * @return bool
     */
    public static function enabled()
    {
        return extension_loaded('event');
    }
    
    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     * @param \EventBase|null $base Use null for an EventBase object to be automatically created.
     *
     * @throws \Icicle\Loop\Exception\UnsupportedError If the event extension is not loaded.
     */
    public function __construct(
        $enableSignals = true,
        EventFactoryInterface $eventFactory = null,
        EventBase $base = null
    ) {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedError(__CLASS__ . ' requires the event extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->base = $base ?: new EventBase();

        parent::__construct($enableSignals, $eventFactory);
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
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory, Event::READ);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory, Event::WRITE);
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
