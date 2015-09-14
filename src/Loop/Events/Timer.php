<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\TimerManagerInterface;

class Timer implements TimerInterface
{
    const MIN_INTERVAL = 0.001; // 1ms minimum interval.
    
    /**
     * @var \Icicle\Loop\Manager\TimerManagerInterface
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
     * @param \Icicle\Loop\Manager\TimerManagerInterface $manager
     * @param int|float $interval Number of seconds until the callback function is called.
     * @param bool $periodic True to repeat the timer, false to only run it once.
     * @param callable $callback Function called when the interval expires.
     * @param mixed[] $args Optional array of arguments to pass the callback function.
     */
    public function __construct(
        TimerManagerInterface $manager,
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function __invoke()
    {
        $this->call();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->manager->isPending($this);
    }

    /**
     * {@inheritdoc}
     */
    public function start()
    {
        $this->manager->start($this);

        if (!$this->referenced) {
            $this->manager->unreference($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->manager->stop($this);
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
     * {@inheritdoc}
     */
    public function getInterval()
    {
        return $this->interval;
    }

    /**
     * {@inheritdoc}
     */
    public function isPeriodic()
    {
        return $this->periodic;
    }
}
