<?php
namespace Icicle\Loop;

use Icicle\Loop\Exception\RunningException;
use Icicle\Loop\Exception\UnsupportedException;
use Icicle\Socket\ReadableSocketInterface;
use Icicle\Socket\SocketInterface;
use Icicle\Socket\WritableSocketInterface;
use Icicle\Structures\UnreferencableObjectStorage;
use Icicle\Timer\TimerInterface;

class LibeventLoop extends AbstractLoop
{
    const MICROSEC_PER_SEC = 1e6;
    
    /**
     * Event base created with event_base_new().
     *
     * @var     resource
     */
    private $base;
    
    /**
     * UnreferencableObjectStorage mapping Timer objects to event resoures.
     *
     * @var     UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @var     resource[int]
     */
    private $readEvents = [];
    
    /**
     * @var     resource[int]
     */
    private $writeEvents = [];
    
    /**
     * @var     resource[int]
     */
    private $signalEvents = [];
    
    /**
     * @var     int[int]
     */
    private $pending = [];
    
    /**
     * @var     Closure
     */
    private $readCallback;
    
    /**
     * @var     Closure
     */
    private $writeCallback;
    
    /**
     * @var     Closure
     */
    private $timerCallback;
    
    /**
     * Determines if the libevent extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('libevent');
    }
    
    /**
     * @throws  UnsupportedException Thrown if the libevent extension is not loaded.
     */
    public function __construct()
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedException('LibeventLoop class requires the libevent extension.');
        } // @codeCoverageIgnoreEnd
        
        parent::__construct();
        
        $this->base = event_base_new();
        $this->timers = new UnreferencableObjectStorage();
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $this->createEvent($signal);
                $event = event_new();
                event_set($event, $signal, EV_SIGNAL | EV_PERSIST, $callback);
                event_base_set($event, $this->base);
                event_add($event);
                $this->signalEvents[$signal] = $event;
            }
        }
        
        $this->readCallback = function ($socket, $what, ReadableSocketInterface $socket) {
            $this->pending[$socket->getId()] &= ~EV_READ;
            if (EV_TIMEOUT & $what) {
                $socket->onTimeout();
            } else {
                $socket->onRead();
            }
        };
        
        $this->writeCallback = function ($socket, $what, WritableSocketInterface $socket) {
            $this->pending[$socket->getId()] &= ~EV_WRITE;
            $socket->onWrite();
        };
        
        $this->timerCallback = function ($socket, $what, TimerInterface $timer) {
            if (!$timer->isPeriodic()) {
                event_free($this->timers[$timer]);
                unset($this->timers[$timer]);
            } else {
                event_add($this->timers[$timer], $timer->getInterval() * self::MICROSEC_PER_SEC);
            }
            
            $timer->call();
        };
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->stop();
        }
        
        foreach ($this->readEvents as $event) {
            event_free($event);
        }
        
        foreach ($this->writeEvents as $event) {
            event_free($event);
        }
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            event_free($this->timers->getInfo());
        }
        
        foreach ($this->signalEvents as $event) {
            event_free($event);
        }
        
        // Need to completely destroy timer events before freeing base or an error is generated.
        $this->timers = null;
        
        event_base_free($this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    public function reInit()
    {
        event_base_reinit($this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        foreach ($this->pending as $pending) {
            if (0 !== $pending) {
                return false;
            }
        }
        
        return !$this->timers->count() && parent::isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function dispatch($blocking)
    {
        $flags = EVLOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EVLOOP_NONBLOCK;
        }
        
        event_base_loop($this->base, $flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * {@inheritdoc}
     */
    public function scheduleReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->readEvents[$id])) {
            $event = event_new();
            event_set($event, $socket->getResource(), EV_READ, $this->readCallback, $socket);
            event_base_set($event, $this->base);
            
            $this->readEvents[$id] = $event;
            
            if (!isset($this->pending[$id])) {
                $this->pending[$id] = 0;
            }
        }
        
        if ($timeout = $socket->getTimeout()) {
            event_add($this->readEvents[$id], $timeout * self::MICROSEC_PER_SEC);
        } else {
            event_add($this->readEvents[$id]);
        }
        
        $this->pending[$id] |= EV_READ;
    }
    
    public function unscheduleReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvents[$id])) {
            event_del($this->readEvents[$id]);
            $this->pending[$id] &= ~EV_READ;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadableSocketScheduled(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->readEvents[$id], $this->pending[$id]) && EV_READ & $this->pending[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function scheduleWritableSocket(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->writeEvents[$id])) {
            $event = event_new();
            event_set($event, $socket->getResource(), EV_WRITE, $this->writeCallback, $socket);
            event_base_set($event, $this->base);
            
            $this->writeEvents[$id] = $event;
            
            if (!isset($this->pending[$id])) {
                $this->pending[$id] = 0;
            }
        }
        
        event_add($this->writeEvents[$id]);
        
        $this->pending[$id] |= EV_WRITE;
    }
    
    /**
     * {@inheritdoc}
     */
    public function unscheduleWritableSocket(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->writeEvents[$id])) {
            event_del($this->writeEvents[$id]);
            $this->pending[$id] &= ~EV_WRITE;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritableSocketScheduled(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->writeEvents[$id], $this->pending[$id]) && EV_WRITE & $this->pending[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeSocket(SocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvents[$id])) {
            event_free($this->readEvents[$id]);
            unset($this->readEvents[$id]);
        }
        
        if (isset($this->writeEvents[$id])) {
            event_free($this->writeEvents[$id]);
            unset($this->writeEvents[$id]);
        }
        
        unset($this->pending[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function addTimer(TimerInterface $timer)
    {
        if (!isset($this->timers[$timer])) {
            $event = event_new();
            event_timer_set($event, $this->timerCallback, $timer);
            event_base_set($event, $this->base);
            
            $this->timers[$timer] = $event;
            
            event_add($event, $timer->getInterval() * self::MICROSEC_PER_SEC);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $event = $this->timers[$timer];
            event_free($event);
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isTimerPending(TimerInterface $timer)
    {
        return isset($this->timers[$timer]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function unreferenceTimer(TimerInterface $timer)
    {
        $this->timers->unreference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function referenceTimer(TimerInterface $timer)
    {
        $this->timers->reference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        parent::clear();
        
        foreach ($this->readEvents as $event) {
            event_free($event);
        }
        
        foreach ($this->writeEvents as $event) {
            event_free($event);
        }
        
        $this->readEvents = [];
        $this->writeEvents = [];
        $this->pending = [];
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            event_free($this->timers->getInfo());
        }
        
        $this->timers = new UnreferencableObjectStorage();
    }
}
