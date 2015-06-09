<?php
namespace Icicle\Loop\Events\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\SocketManagerInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\ResourceBusyException;

abstract class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \EventBase
     */
    private $base;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var \Event[]
     */
    private $events = [];
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $sockets = [];
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * Creates an Event object on the given EventBase for the SocketEventInterface.
     *
     * @param \EventBase $base
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     * @param callable $callback
     *
     * @return \Event
     */
    abstract protected function createEvent(EventBase $base, SocketEventInterface $event, callable $callback);
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param \EventBase $base
     */
    public function __construct(EventFactoryInterface $factory, EventBase $base)
    {
        $this->factory = $factory;
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function create($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyException('A poll has already been created for that resource.');
        }
        
        return $this->sockets[$id] = $this->factory->socket($this, $resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(SocketEventInterface $socket, $timeout = null)
    {
        $id = (int) $socket->getResource();
        
        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedException('Socket event has been freed.');
        }
        
        if (!isset($this->events[$id])) {
            $this->events[$id] = $this->createEvent($this->base, $socket, $this->callback);
        }

        if (null === $timeout) {
            $this->events[$id]->add();
            return;
        }
        
        $timeout = (float) $timeout;
        if (self::MIN_TIMEOUT > $timeout) {
            $timeout = self::MIN_TIMEOUT;
        }

        $this->events[$id]->add($timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id], $this->events[$id]) && $socket === $this->sockets[$id]) {
            $this->events[$id]->del();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return isset($this->sockets[$id], $this->events[$id]) && $socket === $this->sockets[$id] && $this->events[$id]->pending;
    }
    
    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function isFreed(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return !isset($this->sockets[$id]) || $socket !== $this->sockets[$id];
    }
    
    /**
     * {@inheritdoc}
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
     * @return callable
     */
    protected function createCallback()
    {
        return function ($resource, $what, SocketEventInterface $socket) {
            $socket->call(0 !== (Event::TIMEOUT & $what));
        };
    }
}