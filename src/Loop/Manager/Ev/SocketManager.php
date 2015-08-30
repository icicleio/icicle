<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\EvLoop;
use Icicle\Loop\Exception\FreedError;
use Icicle\Loop\Exception\ResourceBusyError;
use Icicle\Loop\Manager\SocketManagerInterface;

class SocketManager implements SocketManagerInterface
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \EvLoop
     */
    private $loop;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var \EvIO[]
     */
    private $events = [];

    /**
     * @var \EvTimer[]
     */
    private $timers = [];

    /**
     * @var callable
     */
    private $socketCallback;

    /**
     * @var callable
     */
    private $timerCallback;

    /**
     * @var int
     */
    private $type;
    
    /**
     * @param \Icicle\Loop\EvLoop $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param int $eventType
     */
    public function __construct(EvLoop $loop, EventFactoryInterface $factory, $eventType)
    {
        $this->factory = $factory;
        $this->loop = $loop->getEvLoop();
        $this->type = $eventType;
        
        $this->socketCallback = function (\EvIO $event) {
            /** @var \Icicle\Loop\Events\SocketEventInterface $socket */
            $socket = $event->data;
            $id = (int) $socket->getResource();

            $event->stop();
            if (isset($this->timers[$id])) {
                $this->timers[$id]->stop();
            }

            $socket->call(false);
        };

        $this->timerCallback = function (\EvTimer $event) {
            /** @var \Icicle\Loop\Events\SocketEventInterface $socket */
            $socket = $event->data;
            $id = (int) $socket->getResource();

            $event->stop();
            $this->events[$id]->stop();

            $socket->call(true);
        };
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            $event->stop();
        }

        foreach ($this->timers as $event) {
            $event->stop();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        foreach ($this->events as $event) {
            if ($event->is_active) {
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
        
        if (isset($this->events[$id])) {
            throw new ResourceBusyError('A poll has already been created for that resource.');
        }

        $socket = $this->factory->socket($this, $resource, $callback);

        $event = $this->loop->io($resource, $this->type, $this->socketCallback, $socket);
        $event->stop();

        $this->events[$id] = $event;

        return $socket;
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(SocketEventInterface $socket, $timeout = 0)
    {
        $id = (int) $socket->getResource();
        
        if (!isset($this->events[$id]) || $socket !== $this->events[$id]->data) {
            throw new FreedError('Socket event has been freed.');
        }

        $this->events[$id]->start();

        if (0 !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }

            if (isset($this->timers[$id])) {
                $this->timers[$id]->set($timeout, 0);
                $this->timers[$id]->start();
            } else {
                $this->timers[$id] = $this->loop->timer($timeout, 0, $this->timerCallback, $socket);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->events[$id]) && $socket === $this->events[$id]->data) {
            $this->events[$id]->stop();

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
        
        return isset($this->events[$id])
            && $socket === $this->events[$id]->data
            && $this->events[$id]->is_active;
    }
    
    /**
     * @inheritdoc
     */
    public function free(SocketEventInterface $socket)
    {
        $id = (int) $socket->getResource();
        
        if (isset($this->events[$id]) && $socket === $this->events[$id]->data) {
            $this->events[$id]->stop();
            unset($this->events[$id]);

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
        
        return !isset($this->events[$id]) || $socket !== $this->events[$id]->data;
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->events as $event) {
            $event->stop();
        }

        foreach ($this->timers as $event) {
            $event->stop();
        }
        
        $this->events = [];
        $this->timers = [];
    }
}