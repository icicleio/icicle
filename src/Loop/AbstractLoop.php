<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop;

use Icicle\Loop\Exception\{RunningError, SignalHandlingDisabledError};
use Icicle\Loop\Manager\{ImmediateManager, IoManager, SignalManager, SharedImmediateManager, TimerManager};
use Icicle\Loop\Watcher\{Immediate, Io, Signal, Timer};
use Icicle\Loop\Structures\CallableQueue;

/**
 * Abstract base class from which loop implementations may be derived. Loop implementations do not have to extend this
 * class, they only need to implement Icicle\Loop\Loop.
 */
abstract class AbstractLoop implements Loop
{
    const DEFAULT_MAX_DEPTH = 1000;

    /**
     * @var \Icicle\Loop\Structures\CallableQueue
     */
    private $callableQueue;
    
    /**
     * @var \Icicle\Loop\Manager\IoManager
     */
    private $pollManager;
    
    /**
     * @var \Icicle\Loop\Manager\IoManager
     */
    private $awaitManager;
    
    /**
     * @var \Icicle\Loop\Manager\TimerManager
     */
    private $timerManager;
    
    /**
     * @var \Icicle\Loop\Manager\ImmediateManager
     */
    private $immediateManager;

    /**
     * @var \Icicle\Loop\Manager\SignalManager|null
     */
    private $signalManager;

    /**
     * @var bool
     */
    private $running = false;
    
    /**
     * Dispatches all pending I/O, timers, and signal callbacks.
     *
     * @param bool $blocking
     */
    abstract protected function dispatch(bool $blocking);
    
    /**
     * @return \Icicle\Loop\Manager\IoManager
     */
    abstract protected function createPollManager(): IoManager;
    
    /**
     * @return \Icicle\Loop\Manager\IoManager
     */
    abstract protected function createAwaitManager(): IoManager;
    
    /**
     * @return \Icicle\Loop\Manager\TimerManager
     */
    abstract protected function createTimerManager(): TimerManager;

    /**
     * @return \Icicle\Loop\Manager\SignalManager
     */
    abstract protected function createSignalManager(): SignalManager;

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     */
    public function __construct($enableSignals = true)
    {
        $this->callableQueue = new CallableQueue(self::DEFAULT_MAX_DEPTH);
        
        $this->immediateManager = $this->createImmediateManager();
        $this->timerManager = $this->createTimerManager();
        
        $this->pollManager = $this->createPollManager();
        $this->awaitManager = $this->createAwaitManager();
        
        if ($enableSignals && extension_loaded('pcntl')) {
            $this->signalManager = $this->createSignalManager();
        }
    }

    /**
     * @return \Icicle\Loop\Manager\IoManager
     *
     * @codeCoverageIgnore
     */
    protected function getPollManager(): IoManager
    {
        return $this->pollManager;
    }
    
    /**
     * @return \Icicle\Loop\Manager\IoManager
     *
     * @codeCoverageIgnore
     */
    protected function getAwaitManager(): IoManager
    {
        return $this->awaitManager;
    }
    
    /**
     * @return \Icicle\Loop\Manager\TimerManager
     *
     * @codeCoverageIgnore
     */
    protected function getTimerManager(): TimerManager
    {
        return $this->timerManager;
    }
    
    /**
     * @return \Icicle\Loop\Manager\ImmediateManager
     *
     * @codeCoverageIgnore
     */
    protected function getImmediateManager(): ImmediateManager
    {
        return $this->immediateManager;
    }

    /**
     * @return \Icicle\Loop\Manager\SignalManager
     *
     * @codeCoverageIgnore
     */
    protected function getSignalManager(): SignalManager
    {
        return $this->signalManager;
    }
    
    /**
     * Determines if there are any pending tasks in the loop.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->pollManager->isEmpty()
            && $this->awaitManager->isEmpty()
            && $this->timerManager->isEmpty()
            && $this->callableQueue->isEmpty()
            && $this->immediateManager->isEmpty()
            && (null === $this->signalManager || $this->signalManager->isEmpty());
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick(bool $blocking = true)
    {
        $blocking = $blocking && $this->callableQueue->isEmpty() && $this->immediateManager->isEmpty();
        
        // Dispatch all pending I/O, timers, and signal callbacks.
        $this->dispatch($blocking);
        
        $this->immediateManager->tick(); // Call the next immediate.
        
        $this->callableQueue->call(); // Call each callback in the tick queue (up to the max depth).
    }
    
    /**
     * {@inheritdoc}
     */
    public function run(callable $initialize = null): bool
    {
        if ($this->isRunning()) {
            throw new RunningError();
        }
        
        $this->running = true;
        
        try {
            if (null !== $initialize) {
                $initialize();
            }

            while ($this->isRunning()) {
                if ($this->isEmpty()) {
                    return false;
                }
                $this->tick();
            }
        } finally {
            $this->stop();
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function queue(callable $callback, array $args = [])
    {
        $this->callableQueue->insert($callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function maxQueueDepth(int $depth): int
    {
        return $this->callableQueue->maxDepth($depth);
    }
    
    /**
     * {@inheritdoc}
     */
    public function poll($resource, callable $callback, bool $persistent = false): Io
    {
        return $this->pollManager->create($resource, $callback, $persistent);
    }
    
    /**
     * {@inheritdoc}
     */
    public function await($resource, callable $callback, bool $persistent = false): Io
    {
        return $this->awaitManager->create($resource, $callback, $persistent);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timer(float $interval, bool $periodic, callable $callback, array $args = []): Timer
    {
        return $this->timerManager->create($interval, $periodic, $callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function immediate(callable $callback, array $args = []): Immediate
    {
        return $this->immediateManager->create($callback, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function signal(int $signo, callable $callback): Signal
    {
        // @codeCoverageIgnoreStart
        if (null === $this->signalManager) {
            throw new SignalHandlingDisabledError();
        } // @codeCoverageIgnoreEnd

        return $this->signalManager->create($signo, $callback);
    }
    
    /**
     * @return bool
     */
    public function signalHandlingEnabled(): bool
    {
        return null !== $this->signalManager;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->callableQueue->clear();
        $this->immediateManager->clear();
        $this->pollManager->clear();
        $this->awaitManager->clear();
        $this->timerManager->clear();

        if (null !== $this->signalManager) {
            $this->signalManager->clear();
        }
    }

    /**
     * @return \Icicle\Loop\Manager\ImmediateManager
     */
    protected function createImmediateManager(): ImmediateManager
    {
        return new SharedImmediateManager();
    }
}
