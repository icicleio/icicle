<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Icicle\Loop\Exception\SignalHandlingDisabledError;
use Icicle\Loop\Manager\Select\SelectIoManager;
use Icicle\Loop\Manager\Select\SelectSignalManager;
use Icicle\Loop\Manager\Select\SelectTimerManager;

/**
 * Uses stream_select(), time_nanosleep(), and pcntl_signal_dispatch() (if available) to implement an event loop that
 * can poll sockets for I/O, create timers, and handle signals.
 */
class SelectLoop extends AbstractLoop
{
    const MICROSEC_PER_SEC = 1e6;
    const DEFAULT_SIGNAL_INTERVAL = 0.25;

    /**
     * @var \Icicle\Loop\Manager\Select\SelectIoManager
     */
    private $pollManager;

    /**
     * @var \Icicle\Loop\Manager\Select\SelectIoManager
     */
    private $awaitManager;

    /**
     * @var \Icicle\Loop\Manager\Select\SelectTimerManager
     */
    private $timerManager;

    /**
     * @var \Icicle\Loop\Manager\Select\SelectSignalManager|null
     */
    private $signalManager;

    /**
     * @var \Icicle\Loop\Watcher\Timer|null
     */
    private $signalTimer;

    /**
     * {@inheritdoc}
     */
    public function reInit() { /* Nothing to be done after fork. */ }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        $timeout = $blocking ? $this->timerManager->getInterval() : 0;

        // Use stream_select() if there are any streams in the loop.
        if (!$this->pollManager->isEmpty() || !$this->awaitManager->isEmpty()) {
            $seconds = (int) $timeout;
            $microseconds = ($timeout - $seconds) * self::MICROSEC_PER_SEC;

            $read = $this->pollManager->getPending();
            $write = $this->awaitManager->getPending();
            $except = null;

            // Error reporting suppressed since stream_select() emits an E_WARNING if it is interrupted by a signal.
            $count = @stream_select($read, $write, $except, null === $timeout ? null : $seconds, $microseconds);

            if ($count) {
                $this->pollManager->handle($read);
                $this->awaitManager->handle($write);
            }
        } elseif (0 < $timeout) { // Otherwise sleep with usleep() if $timeout > 0.
            usleep($timeout * self::MICROSEC_PER_SEC);
        }
        
        $this->timerManager->tick(); // Call any pending timers.
    }

    /**
     * {@inheritdoc}
     */
    protected function createPollManager()
    {
        return $this->pollManager = new SelectIoManager($this);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager()
    {
        return $this->awaitManager = new SelectIoManager($this);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager()
    {
        return $this->timerManager = new SelectTimerManager($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager()
    {
        $this->signalManager = new SelectSignalManager($this);

        $this->signalTimer = $this->timer(self::DEFAULT_SIGNAL_INTERVAL, true, [$this->signalManager, 'tick']);
        $this->signalTimer->unreference();

        return $this->signalManager;
    }

    /**
     * @param float|int $interval
     *
     * @throws \Icicle\Loop\Exception\SignalHandlingDisabledError
     */
    public function signalInterval($interval)
    {
        // @codeCoverageIgnoreStart
        if (null === $this->signalTimer) {
            throw new SignalHandlingDisabledError();
        } // @codeCoverageIgnoreEnd

        $this->signalTimer->stop();
        $this->signalTimer = $this->timer($interval, true, [$this->signalManager, 'tick']);
        $this->signalTimer->unreference();
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        parent::clear();

        if (null !== $this->signalTimer) {
            $this->signalTimer->stop();
            $this->signalTimer = $this->timer($this->signalTimer->getInterval(), true, [$this->signalManager, 'tick']);
            $this->signalTimer->unreference();
        }
    }
}
