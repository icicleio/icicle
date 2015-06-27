<?php
namespace Icicle\Loop\Manager\Libevent;

use Event;
use EventBase;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\ResourceBusyException;
use Icicle\Loop\Manager\SocketManagerInterface;

abstract class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;
    const MICROSEC_PER_SEC = 1e6;

    /**
     * @var resource
     */
    private $base;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var resource[]
     */
    private $events = [];
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface[]
     */
    private $sockets = [];
    
    /**
     * @var bool[]
     */
    private $pending = [];
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * Creates an event resource on the given event base for the SocketEventInterface.
     *
     * @param resource $base Event base resource.
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     * @param callable $callback
     *
     * @return resource Event resource.
     */
    abstract protected function createEvent($base, SocketEventInterface $event, callable $callback);
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param resource $base
     */
    public function __construct(EventFactoryInterface $factory, $base)
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
            event_free($event);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        foreach ($this->pending as $pending) {
            if ($pending) {
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
            throw new ResourceBusyException('A socket event has already been created for that resource.');
        }
        
        $this->sockets[$id] = $this->factory->socket($this, $resource, $callback);
        $this->pending[$id] = false;
        
        return $this->sockets[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(SocketEventInterface $socket, $timeout = 0)
    {
        $id = (int) $socket->getResource();
        
        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedException('Socket event has been freed.');
        }
        
        if (!isset($this->events[$id])) {
            $this->events[$id] = $this->createEvent($this->base, $socket, $this->callback);
        }

        $this->pending[$id] = true;

        if (0 === $timeout) {
            event_add($this->events[$id]);
            return;
        }

        $timeout = (float) $timeout;
        if (self::MIN_TIMEOUT > $timeout) {
            $timeout = self::MIN_TIMEOUT;
        }

        event_add($this->events[$id], $timeout * self::MICROSEC_PER_SEC);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id], $this->events[$id]) && $socket === $this->sockets[$id]) {
            event_del($this->events[$id]);
            $this->pending[$id] = false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return isset($this->sockets[$id], $this->pending[$id])
            && $socket === $this->sockets[$id]
            && $this->pending[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function free(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->sockets[$id]);
            unset($this->pending[$id]);
            
            if (isset($this->events[$id])) {
                event_free($this->events[$id]);
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
            event_free($event);
        }
        
        $this->events = [];
        $this->sockets = [];
        $this->pending = [];
    }
    
    /**
     * @return callable
     */
    protected function createCallback()
    {
        return function ($resource, $what, SocketEventInterface $socket) {
            $this->pending[(int) $resource] = false;
            $socket->call(0 !== (EV_TIMEOUT & $what));
        };
    }
}