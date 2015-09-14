<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Exception\FreedError;
use Icicle\Loop\Exception\ResourceBusyError;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\SelectLoop;

class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \Icicle\Loop\SelectLoop
     */
    private $loop;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $sockets = [];
    
    /**
     * @var resource[]
     */
    private $pending = [];
    
    /**
     * @var \Icicle\Loop\Events\TimerInterface[]
     */
    private $timers = [];

    /**
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $unreferenced = [];
    
    /**
     * @var callable
     */
    private $timerCallback;
    
    /**
     * @param \Icicle\Loop\SelectLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(SelectLoop $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        
        $this->timerCallback = function (SocketEventInterface $socket) {
            $id = (int) $socket->getResource();
            unset($this->pending[$id]);
            unset($this->timers[$id]);

            $socket->call(true);
        };
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyError('A socket event has already been created for this resource.');
        }
        
        return $this->sockets[$id] = $this->factory->socket($this, $resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(SocketEventInterface $socket, $timeout = 0)
    {
        $resource = $socket->getResource();
        $id = (int) $resource;
        
        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedError('Poll has been freed.');
        }
        
        $this->pending[$id] = $resource;
        
        if (0 !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            
            $this->timers[$id] = $this->loop->timer($timeout, false, $this->timerCallback, [$socket]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->pending[$id]);
            
            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return isset($this->sockets[$id]) && $socket === $this->sockets[$id] && isset($this->pending[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function free(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->sockets[$id], $this->pending[$id], $this->unreferenced[$id]);
            
            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
                unset($this->timers[$id]);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return !isset($this->sockets[$id]) || $socket !== $this->sockets[$id];
    }
    
    /**
     * @return resource[]
     *
     * @internal
     */
    public function getPending()
    {
        return $this->pending;
    }
    
    /**
     * @param resource[] $active
     *
     * @internal
     */
    public function handle(array $active)
    {
        foreach ($active as $id => $resource) {
            if (isset($this->sockets[$id], $this->pending[$id])) { // Event may have been removed from a previous call.
                unset($this->pending[$id]);
                
                if (isset($this->timers[$id])) {
                    $this->timers[$id]->stop();
                }
                
                $this->sockets[$id]->call(false);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reference(SocketEventInterface $socket)
    {
        unset($this->unreferenced[(int) $socket->getResource()]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();

        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            $this->unreferenced[$id] = $socket;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        if (empty($this->unreferenced)) {
            return empty($this->pending);
        }

        foreach ($this->pending as $pending) {
            if (!isset($this->unreferenced[(int) $pending])) {
                return false;
            }
        }

        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->sockets = [];
        $this->pending = [];
        $this->unreferenced = [];
        
        foreach ($this->timers as $timer) {
            $timer->stop();
        }
        
        $this->timers = [];
    }
}
