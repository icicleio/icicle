<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

use Icicle\Loop\Manager\IoManager;

/**
 * Represents read and write (poll and await) io events.
 */
class Io extends Watcher
{
    /**
     * @var \Icicle\Loop\Manager\IoManager
     */
    private $manager;
    
    /**
     * @var resource
     */
    private $resource;
    
    /**
     * @var callable
     */
    private $callback;

    /**
     * @var bool
     */
    private $persistent;
    
    /**
     * @param \Icicle\Loop\Manager\IoManager $manager
     * @param resource $resource
     * @param callable $callback
     * @param bool $persistent
     * @param mixed $data Optional data to associate with the watcher.
     */
    public function __construct(IoManager $manager, $resource, callable $callback, $persistent = false, $data = null)
    {
        $this->manager = $manager;
        $this->resource = $resource;
        $this->callback = $callback;
        $this->persistent = (bool) $persistent;

        if (null !== $data) {
            $this->setData($data);
        }
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     *
     * @param bool $expired
     */
    public function call($expired)
    {
        $callback = $this->callback;
        $callback($this->resource, $expired, $this);
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function __invoke($expired)
    {
        $this->call($expired);
    }

    /**
     * Sets the callback invoked when events occur.
     *
     * @param callable $callback
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * Listens for available data to read or space to write, invoking the callback when an event occurs.
     *
     * @param float|int $timeout If no data is received or space is not made available to write after the given timeout,
     *     the callback will be invoked with the $expired parameter set to true. Use 0 for no timeout.
     */
    public function listen($timeout = 0)
    {
        $this->manager->listen($this, $timeout);
    }
    
    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->manager->isPending($this);
    }
    
    /**
     * Determines if the event has been freed from the loop.
     *
     * @return bool
     */
    public function isFreed()
    {
        return $this->manager->isFreed($this);
    }
    
    /**
     * Cancels listening for events.
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }
    
    /**
     * Frees the event from the loop.
     */
    public function free()
    {
        $this->manager->free($this);
    }

    /**
     * @return bool
     */
    public function isPersistent()
    {
        return $this->persistent;
    }
    
    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * An unreferenced event will allow the event loop to exit if no other watchers are pending.
     */
    public function unreference()
    {
        $this->manager->unreference($this);
    }

    /**
     * Adds a reference to the event, causing the event loop to continue to run as long as the watcher is still pending.
     */
    public function reference()
    {
        $this->manager->reference($this);
    }
}
