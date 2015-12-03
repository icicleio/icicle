<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

use Icicle\Loop\Manager\TimerManager;

class Timer implements Watcher
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
     * @var mixed[]|null
     */
    private $args;
    
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
     * @param mixed[] $args Optional array of arguments to pass the callback function.
     */
    public function __construct(
        TimerManager $manager,
        $interval,
        $periodic,
        callable $callback,
        array $args = []
    ) {
        $this->manager = $manager;
        $this->interval = (float) $interval;
        $this->periodic = (bool) $periodic;
        $this->callback = $callback;
        $this->args = $args;

        if (self::MIN_INTERVAL > $this->interval) {
            $this->interval = self::MIN_INTERVAL;
        }
    }

    /**
     * @param callable $callback
     * @param mixed ...$args
     */
    public function setCallback(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        $this->callback = $callback;
        $this->args = $args;
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function call()
    {
        if (empty($this->args)) {
            $callback = $this->callback;
            $callback();
        } else {
            call_user_func_array($this->callback, $this->args);
        }
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
     * {@inheritdoc}
     */
    public function unreference()
    {
        $this->referenced = false;
        $this->manager->unreference($this);
    }
    
    /**
     * {@inheritdoc}
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
