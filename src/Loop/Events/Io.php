<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\IoManager;

/**
 * Represents read and write (poll and await) io events.
 */
class Io
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
     * {@inheritdoc}
     */
    public function call(bool $expired)
    {
        ($this->callback)($this->resource, $expired);
    }
    
    /**
     * {@inheritdoc}
     */
    public function __invoke(bool $expired)
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
    public function listen(float $timeout = 0)
    {
        $this->manager->listen($this, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->manager->isPending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed(): bool
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
