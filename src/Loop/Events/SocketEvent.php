<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Exception\NonResourceError;
use Icicle\Loop\Manager\SocketManagerInterface;

/**
 * Represents read and write (poll and await) socket events.
 */
class SocketEvent implements SocketEventInterface
{
    /**
     * @var \Icicle\Loop\Manager\SocketManagerInterface
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
     * @param \Icicle\Loop\Manager\SocketManagerInterface $manager
     * @param resource $resource
     * @param callable $callback
     *
     * @throws \Icicle\Loop\Exception\NonResourceError If a non-resource is given for $resource.
     */
    public function __construct(SocketManagerInterface $manager, $resource, callable $callback)
    {
        if (!is_resource($resource)) {
            throw new NonResourceError('Must provide a socket or stream resource.');
        }
        
        $this->manager = $manager;
        $this->resource = $resource;
        $this->callback = $callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function call($expired)
    {
        $callback = $this->callback;
        $callback($this->resource, $expired);
    }
    
    /**
     * {@inheritdoc}
     */
    public function __invoke($expired)
    {
        $this->call($expired);
    }

    /**
     * {@inheritdoc}
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function listen($timeout = 0)
    {
        $this->manager->listen($this, $timeout);
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
    public function isFreed()
    {
        return $this->manager->isFreed($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }
    
    /**
     * {@inheritdoc}
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
