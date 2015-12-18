<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

use Icicle\Loop\Manager\SignalManager;

class Signal extends Watcher
{
    /**
     * @var \Icicle\Loop\Manager\SignalManager
     */
    private $manager;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var int
     */
    private $signo;

    /**
     * @var bool
     */
    private $referenced = false;

    /**
     * @param \Icicle\Loop\Manager\SignalManager $manager
     * @param int $signo Signal number.
     * @param callable $callback Callback invoked when the signal is received.
     * @param mixed $data Optional data to associate with the watcher.
     */
    public function __construct(SignalManager $manager, $signo, callable $callback, $data = null)
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->signo = (int) $signo;

        if (null !== $data) {
            $this->setData($data);
        }
    }

    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function call()
    {
        $callback = $this->callback;
        $callback($this->signo, $this);
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
     * Sets the callback invoked when a signal is received.
     *
     * @param callable $callback
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Enables listening for signals to arrive.
     */
    public function enable()
    {
        $this->manager->enable($this);

        if ($this->referenced) {
            $this->manager->reference($this);
        }
    }

    /**
     * Disables listening for signals to arrive.
     */
    public function disable()
    {
        $this->manager->disable($this);
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->manager->isEnabled($this);
    }

    /**
     * @return int
     */
    public function getSignal()
    {
        return $this->signo;
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
}
