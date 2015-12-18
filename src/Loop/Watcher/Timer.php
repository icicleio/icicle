<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

use Icicle\Loop\Manager\TimerManager;

class Timer extends Watcher
{
    const MIN_INTERVAL = 0.001; // 1ms minimum interval.
    
    /**
     * @var \Icicle\Loop\Manager\TimerManager
     */
    private $manager;
    
    /**
     * Callback function to be called when the timer expires.
     *
     * @var callable
     */
    private $callback;

    /**
     * Number of seconds until the timer is called.
     *
     * @var float
     */
    private $interval;
    
    /**
     * True if the timer is periodic, false if the timer should only be called once.
     *
     * @var bool
     */
    private $periodic;

    /**
     * @var bool
     */
    private $referenced = true;
    
    /**
     * @param \Icicle\Loop\Manager\TimerManager $manager
     * @param int|float $interval Number of seconds until the callback function is called.
     * @param bool $periodic True to repeat the timer, false to only run it once.
     * @param callable $callback Function called when the interval expires.
     * @param mixed $data Optional data to associate with the watcher.
     */
    public function __construct(
        TimerManager $manager,
        $interval,
        $periodic,
        callable $callback,
        $data = null
    ) {
        $this->manager = $manager;
        $this->interval = (float) $interval;
        $this->periodic = (bool) $periodic;
        $this->callback = $callback;

        if (self::MIN_INTERVAL > $this->interval) {
            $this->interval = self::MIN_INTERVAL;
        }

        if (null !== $data) {
            $this->setData($data);
        }
    }

    /**
     * @param callable $callback
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function call()
    {
        $callback = $this->callback;
        $callback($this);
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function __invoke()
    {
        $this->call();
    }
    
    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->manager->isPending($this);
    }

    /**
     * Starts the timer if it not pending.
     */
    public function start()
    {
        $this->manager->start($this);

        if (!$this->referenced) {
            $this->manager->unreference($this);
        }
    }

    /**
     * Stops the timer if it is pending.
     */
    public function stop()
    {
        $this->manager->stop($this);
    }

    /**
     * If the timer is running, restarts the timer as though it were just started. Otherwise the timer is started again.
     */
    public function again()
    {
        if ($this->isPending()) {
            $this->stop();
        }

        $this->start();
    }
    
    /**
     * An unreferenced event will allow the event loop to exit if no other watchers are pending.
     */
    public function unreference()
    {
        $this->referenced = false;
        $this->manager->unreference($this);
    }
    
    /**
     * Adds a reference to the event, causing the event loop to continue to run as long as the watcher is still pending.
     */
    public function reference()
    {
        $this->referenced = true;
        $this->manager->reference($this);
    }
    
    /**
     * @return float
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * @return bool
     */
    public function isPeriodic()
    {
        return $this->periodic;
    }
}
