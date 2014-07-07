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
     * @var     ReadableSocketInterface[int]
     */
    private $readPending = [];
    
    /**
     * @var     WritableSocketInterface[int]
     */
    private $writePending = [];
    
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
     * Determines if the PECL libevent extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('libevent');
    }
    
    /**
     * @throws  UnsupportedException Thrown if the PECL libevent extension is not loaded.
     */
    public function __construct()
    {
        if (!self::enabled()) {
            throw new UnsupportedException('LibeventLoop class requires the libevent extension.');
        }
        
        parent::__construct();
        
        $this->base = event_base_new();
        $this->timers = new UnreferencableObjectStorage();
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $event = event_new();
                event_set($event, $signal, EV_SIGNAL | EV_PERSIST, $callback);
                event_base_set($event, $this->base);
                event_add($event);
                $this->signalEvents[$signal] = $event;
            }
        }
        
        $this->readCallback = function ($_, $what, ReadableSocketInterface $socket) {
            if (EV_TIMEOUT & $what) {
                $socket->onTimeout();
            } else {
                $socket->onRead();
            }
        };
        
        $this->writeCallback = function ($_, $_, WritableSocketInterface $socket) {
            $socket->onWrite();
        };
        
        $this->timerCallback = function ($_, $_, TimerInterface $timer) {
            if (!$timer->isPeriodic()) {
                $event = $this->timers[$timer];
                event_del($event);
                event_free($event);
                unset($this->timers[$timer]);
            } else {
                event_add($this->timers[$timer], $timer->getInterval() * self::MICROSEC_PER_SEC);
            }
            
            $timer->call();
        };
    }
    
    /**
     */
    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->stop();
        }
        
        foreach ($this->readEvents as $event) {
            event_del($event);
            event_free($event);
        }
        
        foreach ($this->writeEvents as $event) {
            event_del($event);
            event_free($event);
        }
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $event = $this->timers->getInfo();
            event_del($event);
            event_free($event);
        }
        
        foreach ($this->signalEvents as $event) {
            event_del($event);
            event_free($event);
        }
        
        // Need to completely destroy timer events before freeing base.
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
        return empty($this->readEvents) && empty($this->writeEvents) && !$this->timers->count() && parent::isEmpty();
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
    public function addReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->readEvents[$id])) {
            $event = event_new();
            event_set($event, $socket->getResource(), EV_READ | EV_PERSIST, $this->readCallback, $socket);
            event_base_set($event, $this->base);
            
            if ($timeout = $socket->getTimeout()) {
                event_add($event, $timeout * self::MICROSEC_PER_SEC);
            } else {
                event_add($event);
            }
            
            $this->readEvents[$id] = $event;
            $this->readPending[$id] = $socket;
        }
    }
    
    public function pauseReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvent[$id])) {
            event_del($this->readEvent[$id]);
            unset($this->readPending[$id]);
        }
    }
    
    public function resumeReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvents[$id]) && !isset($this->readPending[$id])) {
            if ($timeout = $socket->getTimeout()) {
                event_add($this->readEvents[$id], $timeout * self::MICROSEC_PER_SEC);
            } else {
                event_add($this->readEvents[$id]);
            }
            $this->readPending[$id] = $socket;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadableSocketPending(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->readEvents[$id], $this->readPending[$id]);
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
        }
        
        event_add($this->writeEvents[$id]);
        $this->writePending[$id] = $socket;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritableSocketScheduled(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->writeEvents[$id], $this->writePending[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function unscheduleWritableSocket(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->writeEvents[$id])) {
            event_del($this->writeEvents[$id]);
            unset($this->writePending[$id]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function containsSocket(SocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->readEvents[$id]) || isset($this->writeEvents[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeSocket(SocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvents[$id])) {
            event_del($this->readEvents[$id]);
            event_free($this->readEvents[$id]);
            unset($this->readEvents[$id]);
            unset($this->readPending[$id]);
        }
        
        if (isset($this->writeEvents[$id])) {
            event_del($this->writeEvents[$id]);
            event_free($this->writeEvents[$id]);
            unset($this->writeEvents[$id]);
            unset($this->writePending[$id]);
        }
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
            event_del($event);
            event_free($event);
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer)
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
            event_del($event);
            event_free($event);
        }
        
        foreach ($this->writeEvents as $event) {
            event_del($event);
            event_free($event);
        }
        
        $this->readEvents = [];
        $this->writeEvents = [];
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $event = $this->timers->getInfo();
            event_del($event);
            event_free($event);
        }
        
        $this->timers = new UnreferencableObjectStorage();
    }
}
