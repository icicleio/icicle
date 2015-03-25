<?php
namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\ResourceBusyException;
use Icicle\Loop\Manager\SocketManagerInterface;

abstract class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var EventBase
     */
    private $base;
    
    /**
     * @var Event[]
     */
    private $events = [];
    
    /**
     * @var SocketEventInterface[]
     */
    private $sockets = [];
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * Create a SocketEventInterface object for the given resource.
     *
     * @param   resource $resource Stream socket resource.
     * @param   callable $callback
     *
     * @return  SocketEventInterface
     */
    abstract protected function createSocketEvent($resource, callable $callback);
    
    /**
     * Creates an Event object on the given EventBase for the SocketEventInterface.
     *
     * @param   EventBase $base
     * @param   SocketEventInterface $event
     * @param   callable $callback
     *
     * @return  Event
     */
    abstract protected function createEvent(EventBase $base, SocketEventInterface $event, callable $callback);
    
    /**
     * @param   EventBase $base
     */
    public function __construct(EventBase $base)
    {
        $this->base = $base;
        
        $this->callback = $this->createCallback();
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            $event->free();
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        foreach ($this->events as $event) {
            if ($event->pending) {
                return false;
            }
        }

        return true;
    }
    
    /**
     * @inheritdoc
     */
    public function create($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyException('A poll has already been created for that resource.');
        }
        
        return $this->sockets[$id] = $this->createSocketEvent($resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function listen(SocketEventInterface $socket, $timeout = null)
    {
        $id = (int) $socket->getResource();
        
        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedException('Socket event has been freed.');
        }
        
        if (!isset($this->events[$id])) {
            //$this->events[$id] = new Event($this->base, $socket->getResource(), Event::READ, $this->callback, $socket);
            $this->events[$id] = $this->createEvent($this->base, $socket, $this->callback);
        }
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            $this->events[$id]->add($timeout);
        } else {
            $this->events[$id]->add();
        }
    }
    
    /**
     * @inheritdoc
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id], $this->events[$id]) && $socket === $this->sockets[$id]) {
            $this->events[$id]->del();
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isPending(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return isset($this->sockets[$id], $this->events[$id]) && $socket === $this->sockets[$id] && $this->events[$id]->pending;
    }
    
    /**
     * @inheritdoc
     */
    public function free(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->sockets[$id]);
            
            if (isset($this->events[$id])) {
                $this->events[$id]->free();
                unset($this->events[$id]);
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isFreed(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return !isset($this->sockets[$id]) || $socket !== $this->sockets[$id];
    }
    
    /**
     * @inheritdoc
     */
    public function clear()
    {
        foreach ($this->events as $event) {
            $event->free();
        }
        
        $this->events = [];
        $this->sockets = [];
    }
    
    /**
     * @return  callable
     */
    protected function createCallback()
    {
        return function ($resource, $what, SocketEventInterface $socket) {
            $socket->call($resource, 0 !== (Event::TIMEOUT & $what));
        };
    }
}