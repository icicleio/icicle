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
class Io implements Watcher
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
     * @param \Icicle\Loop\Manager\IoManager $manager
     * @param resource $resource
     * @param callable $callback
     */
    public function __construct(IoManager $manager, $resource, callable $callback)
    {
        $this->manager = $manager;
        $this->resource = $resource;
        $this->callback = $callback;
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function call($expired)
    {
        $callback = $this->callback;
        $callback($this->resource, $expired);
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
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function unreference()
    {
        $this->manager->unreference($this);
    }

    /**
     * {@inheritdoc}
     */
    public function reference()
    {
        $this->manager->reference($this);
    }
}
