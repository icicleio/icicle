<?php
namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\ResourceBusyException;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\SelectLoop;

abstract class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var SelectLoop
     */
    private $loop;
    
    /**
     * @var SocketEventInterface[]
     */
    private $sockets = [];
    
    /**
     * @var resource[]
     */
    private $pending = [];
    
    /**
     * @var TimerInterface[]
     */
    private $timers = [];
    
    /**
     * @var callable
     */
    private $timerCallback;
    
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
     * @param   SelectLoop $loop
     */
    public function __construct(SelectLoop $loop)
    {
        $this->loop = $loop;
        
        $this->timerCallback = $this->createTimerCallback();
    }
    
    /**
     * @inheritdoc
     */
    public function create($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->sockets[$id])) {
            throw new ResourceBusyException('A poll has already been created for this resource.');
        }
        
        return $this->sockets[$id] = $this->createSocketEvent($resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function listen(SocketEventInterface $socket, $timeout = null)
    {
        $resource = $socket->getResource();
        $id = (int) $resource;
        
        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedException('Poll has been freed.');
        }
        
        $this->pending[$id] = $resource;
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            
            $this->timers[$id] = $this->loop->createTimer($this->timerCallback, $timeout, false, [$socket]);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->sockets[$id]) && $socket === $this->sockets[$id]) {
            unset($this->pending[$id]);
            
            if (isset($this->timers[$id])) {
                $this->timers[$id]->cancel();
                unset($this->timers[$id]);
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isPending(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        return isset($this->sockets[$id]) && $socket === $this->sockets[$id] && isset($this->pending[$id]);
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
            
            if (isset($this->timers[$id])) {
                $this->timers[$id]->cancel();
                unset($this->timers[$id]);
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
                    $this->timers[$id]->cancel();
                    unset($this->timers[$id]);
                }
                
                $this->sockets[$id]->call($resource, false);
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        return empty($this->pending);
    }
    
    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->sockets = [];
        $this->pending = [];
        
        foreach ($this->timers as $timer) {
            $timer->cancel();
        }
        
        $this->timers = [];
    }

    /**
     * @return callable
     */
    protected function createTimerCallback()
    {
        return function (SocketEventInterface $socket) {
            $resource = $socket->getResource();
            $id = (int) $resource;
            unset($this->pending[$id]);
            unset($this->timers[$id]);
            
            $socket->call($resource, true);
        };
    }
}
