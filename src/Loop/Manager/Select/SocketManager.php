<?php
namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Exception\FreedError;
use Icicle\Loop\Exception\ResourceBusyError;
use Icicle\Loop\LoopInterface;
use Icicle\Loop\Manager\SocketManagerInterface;

class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \Icicle\Loop\LoopInterface
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
     * @var callable
     */
    private $timerCallback;
    
    /**
     * @param \Icicle\Loop\LoopInterface $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        
        $this->timerCallback = $this->createTimerCallback();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($resource, callable $callback): SocketEventInterface
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
    public function listen(SocketEventInterface $socket, float $timeout = 0)
    {
        $resource = $socket->getResource();
        $id = (int) $resource;
        
        if (!isset($this->sockets[$id]) || $socket !== $this->sockets[$id]) {
            throw new FreedError('Poll has been freed.');
        }
        
        $this->pending[$id] = $resource;
        
        if ($timeout) {
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
    public function isPending(SocketEventInterface $socket): bool
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
            unset($this->sockets[$id]);
            unset($this->pending[$id]);
            
            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
                unset($this->timers[$id]);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed(SocketEventInterface $socket): bool
    {
        $id = (int) $socket->getResource();
        
        return !isset($this->sockets[$id]) || $socket !== $this->sockets[$id];
    }
    
    /**
     * @return resource[]
     *
     * @internal
     */
    public function getPending(): array
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
    public function isEmpty(): bool
    {
        return empty($this->pending);
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->sockets = [];
        $this->pending = [];
        
        foreach ($this->timers as $timer) {
            $timer->stop();
        }
        
        $this->timers = [];
    }

    /**
     * @return callable
     */
    protected function createTimerCallback(): callable
    {
        return function (SocketEventInterface $socket) {
            $id = (int) $socket->getResource();
            unset($this->pending[$id]);
            unset($this->timers[$id]);
            
            $socket->call(true);
        };
    }
}
