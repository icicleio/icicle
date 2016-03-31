<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Exception\FreedError;
use Icicle\Loop\Exception\ResourceBusyError;
use Icicle\Loop\Manager\IoManager;
use Icicle\Loop\SelectLoop;
use Icicle\Loop\Watcher\Io;
use Icicle\Loop\Watcher\Timer;

class SelectIoManager implements IoManager
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \Icicle\Loop\SelectLoop
     */
    private $loop;

    /**
     * @var \Icicle\Loop\Watcher\Io[]
     */
    private $sockets = [];
    
    /**
     * @var resource[]
     */
    private $pending = [];
    
    /**
     * @var \Icicle\Loop\Watcher\Timer[]
     */
    private $timers = [];

    /**
     * @var \Icicle\Loop\Watcher\Io[]
     */
    private $unreferenced = [];
    
    /**
     * @var callable
     */
    private $timerCallback;
    
    /**
     * @param \Icicle\Loop\SelectLoop $loop
     */
    public function __construct(SelectLoop $loop)
    {
        $this->loop = $loop;

        $this->timerCallback = function (Timer $timer) {
            /** @var \Icicle\Loop\Watcher\Io $io */
            $io = $timer->getData();
            $id = (int) $io->getResource();
            unset($this->pending[$id]);
            unset($this->timers[$id]);

            $io->call(true);
        };
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($resource, callable $callback, $persistent = false, $data = null)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyError();
        }
        
        return $this->sockets[$id] = new Io($this, $resource, $callback, $persistent, $data);
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(Io $io, $timeout = 0)
    {
        $resource = $io->getResource();
        $id = (int) $resource;
        
        if (!isset($this->sockets[$id]) || $io !== $this->sockets[$id]) {
            throw new FreedError();
        }
        
        $this->pending[$id] = $resource;

        if (isset($this->timers[$id])) {
            $this->timers[$id]->stop();
        }

        if (0 !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            
            $this->timers[$id] = $this->loop->timer($timeout, false, $this->timerCallback, $io);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Io $io)
    {
        $id = (int) $io->getResource();
        
        if (isset($this->sockets[$id]) && $io === $this->sockets[$id]) {
            unset($this->pending[$id]);
            
            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Io $io)
    {
        $id = (int) $io->getResource();
        
        return isset($this->sockets[$id]) && $io === $this->sockets[$id] && isset($this->pending[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function free(Io $io)
    {
        $id = (int) $io->getResource();
        
        if (isset($this->sockets[$id]) && $io === $this->sockets[$id]) {
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
    public function isFreed(Io $io)
    {
        $id = (int) $io->getResource();
        
        return !isset($this->sockets[$id]) || $io !== $this->sockets[$id];
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
        foreach ($active as $resource) {
            $id = (int) $resource;
            if (isset($this->sockets[$id], $this->pending[$id])) { // Event may have been removed from a previous call.
                if (!$this->sockets[$id]->isPersistent()) {
                    unset($this->pending[$id]);
                    if (isset($this->timers[$id])) {
                        $this->timers[$id]->stop();
                    }
                } elseif (isset($this->timers[$id])) {
                    $this->timers[$id]->again();
                }

                $this->sockets[$id]->call(false);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reference(Io $io)
    {
        unset($this->unreferenced[(int) $io->getResource()]);
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(Io $io)
    {
        $id = (int) $io->getResource();

        if (isset($this->sockets[$id]) && $io === $this->sockets[$id]) {
            $this->unreferenced[$id] = $io;
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
