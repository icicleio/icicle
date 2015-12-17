<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\EvLoop;
use Icicle\Loop\Exception\FreedError;
use Icicle\Loop\Exception\ResourceBusyError;
use Icicle\Loop\Manager\IoManager;
use Icicle\Loop\Watcher\Io;

class EvIoManager implements IoManager
{
    const MIN_TIMEOUT = 0.001;

    /**
     * @var \EvLoop
     */
    private $loop;

    /**
     * @var \EvIO[]
     */
    private $events = [];

    /**
     * @var \EvTimer[]
     */
    private $timers = [];

    /**
     * @var \Icicle\Loop\Watcher\Io[]
     */
    private $unreferenced = [];

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
     * @param int $eventType
     */
    public function __construct(EvLoop $loop, $eventType)
    {
        $this->loop = $loop->getEvLoop();
        $this->type = $eventType;
        
        $this->socketCallback = function (\EvIO $event) {
            /** @var \Icicle\Loop\Watcher\Io $io */
            $io = $event->data;
            $id = (int) $io->getResource();

            if ($io->isPersistent()) {
                if (isset($this->timers[$id])) {
                    $this->timers[$id]->again();
                }
            } else {
                $event->stop();
                if (isset($this->timers[$id])) {
                    $this->timers[$id]->stop();
                }
            }

            $io->call(false);
        };

        $this->timerCallback = function (\EvTimer $event) {
            /** @var \Icicle\Loop\Watcher\Io $io */
            $io = $event->data;
            $id = (int) $io->getResource();

            $event->stop();
            $this->events[$id]->stop();

            $io->call(true);
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
        foreach ($this->events as $id => $event) {
            if ($event->is_active && !isset($this->unreferenced[$id])) {
                return false;
            }
        }

        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($resource, callable $callback, $persistent = false, $data = null)
    {
        $id = (int) $resource;
        
        if (isset($this->events[$id])) {
            throw new ResourceBusyError();
        }

        $socket = new Io($this, $resource, $callback, $persistent, $data);

        $event = $this->loop->io($resource, $this->type, $this->socketCallback, $socket);
        $event->stop();

        $this->events[$id] = $event;

        return $socket;
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen(Io $io, $timeout = 0)
    {
        $id = (int) $io->getResource();
        
        if (!isset($this->events[$id]) || $io !== $this->events[$id]->data) {
            throw new FreedError();
        }

        $this->events[$id]->start();

        if ($timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }

            if (isset($this->timers[$id])) {
                $this->timers[$id]->set($timeout, $timeout);
                $this->timers[$id]->start();
            } else {
                $this->timers[$id] = $this->loop->timer($timeout, $timeout, $this->timerCallback, $io);
            }
        } elseif (isset($this->timers[$id])) {
            $this->timers[$id]->stop();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Io $io)
    {
        $id = (int) $io->getResource();
        
        if (isset($this->events[$id]) && $io === $this->events[$id]->data) {
            $this->events[$id]->stop();

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
        
        return isset($this->events[$id])
            && $io === $this->events[$id]->data
            && $this->events[$id]->is_active;
    }
    
    /**
     * @inheritdoc
     */
    public function free(Io $io)
    {
        $id = (int) $io->getResource();
        
        if (isset($this->events[$id]) && $io === $this->events[$id]->data) {
            $this->events[$id]->stop();
            unset($this->events[$id], $this->unreferenced[$id]);

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
        
        return !isset($this->events[$id]) || $io !== $this->events[$id]->data;
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

        if (isset($this->events[$id]) && $io === $this->events[$id]->data) {
            $this->unreferenced[$id] = $io;
        }
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
        $this->unreferenced = [];
        $this->timers = [];
    }
}