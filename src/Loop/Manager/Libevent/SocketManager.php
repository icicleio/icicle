<?php
namespace Icicle\Loop\Manager\Libevent;

use Event;
use EventBase;
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
     * @var resource[]
     */
    private $events = [];
    
    /**
     * @var SocketEventInterface[]
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
     * Create a SocketEventInterface object for the given resource.
     *
     * @param   resource $resource Stream socket resource.
     * @param   callable $callback
     *
     * @return  SocketEventInterface
     */
    abstract protected function createSocketEvent($resource, callable $callback);
    
    /**
     * Creates an event resource on the given event base for the SocketEventInterface.
     *
     * @param   resource $base Event base resource.
     * @param   SocketEventInterface $event
     * @param   callable $callback
     *
     * @return  resource Event resource.
     */
    abstract protected function createEvent($base, SocketEventInterface $event, callable $callback);
    
    /**
     * @param   EventBase $base
     */
    public function __construct($base)
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
            event_free($event);
        }
    }
    
    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function create($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyException('An event has already been created for that resource.');
        }
        
        $this->sockets[$id] = $this->createSocketEvent($resource, $callback);
        $this->pending[$id] = false;
        
        return $this->sockets[$id];
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
            $this->events[$id] = $this->createEvent($this->base, $socket, $this->callback);
        }
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            event_add($this->events[$id], $timeout * self::MICROSEC_PER_SEC);
        } else {
            event_add($this->events[$id]);
        }
        
        $this->pending[$id] = true;
    }
    
    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function isPending(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return isset($this->sockets[$id], $this->pending[$id]) && $socket === $this->sockets[$id] && $this->pending[$id];
    }
    
    /**
     * @inheritdoc
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
            event_free($event);
        }
        
        $this->events = [];
        $this->sockets = [];
        $this->pending = [];
    }
    
    /**
     * @return  callable
     */
    protected function createCallback()
    {
        return function ($resource, $what, SocketEventInterface $socket) {
            $this->pending[(int) $resource] = false;
            $socket->call($resource, 0 !== (EV_TIMEOUT & $what));
        };
    }
}