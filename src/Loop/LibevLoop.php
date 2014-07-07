<?php
namespace Icicle\Loop;

use Ev;
use EvIO;
use EvLoop;
use EvSignal;
use EvTimer;
use Icicle\Loop\Exception\RunningException;
use Icicle\Loop\Exception\UnsupportedException;
use Icicle\Socket\ReadableSocketInterface;
use Icicle\Socket\SocketInterface;
use Icicle\Socket\WritableSocketInterface;
use Icicle\Structures\UnreferencableObjectStorage;
use Icicle\Timer\Timer;
use Icicle\Timer\TimerInterface;

class LibevLoop extends AbstractLoop
{
    const TIMEOUT_TIMER_INTERVAL = 1;
    
    /**
     * UnreferencableObjectStorage mapping Timer objects to EvTimer objects.
     *
     * @var     UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @var     EvIO[int]
     */
    private $readEvents = [];
    
    /**
     * @var     EvIO[int]
     */
    private $writeEvents = [];
    
    /**
     * @var     EvSignal[int]
     */
    private $signalEvents = [];
    
    /**
     * @var     int[int]
     */
    private $timeouts = [];
    
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
     * @var     Timer|null
     */
    private $timer;
    
    /**
     * Determines if the PECL ev extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('ev');
    }
    
    /**
     * @throws  UnsupportedException Thrown if the PECL ev extension is not loaded.
     */
    public function __construct()
    {
        if (!self::enabled()) {
            throw new UnsupportedException('LibevLoop class requires the ev extension.');
        }
        
        parent::__construct();
        
        $this->timers = new UnreferencableObjectStorage();
        
        if ($this->signalHandlingEnabled()) {
            $handler = $this->createSignalCallback();
            
            $callback = function (EvSignal $event) use ($handler) {
                $handler($event->signum);
            };
            
            foreach ($this->getSignalList() as $signal) {
                $event = new EvSignal($signal, $callback);
                $event->start();
                $this->signalEvents[$signal] = $event;
            }
        }
        
        $this->readCallback = function (EvIO $event) {
            if ($this->timeouts[$id]) {
                $this->timeouts[$id] = $time + $event->data->getTimeout();
            }
            $event->data->onRead();
        };
        
        $this->writeCallback = function (EvIO $event) {
            $event->data->onWrite();
        };
        
        $this->timerCallback = function (EvTimer $event) {
            $timer = $event->data;
            if (!$timer->isPeriodic()) {
                $this->timers[$timer]->stop();
                unset($this->timers[$timer]);
            }
            
            $timer->call();
        };
        
        $this->schedule(function () {
            $this->timer = Timer::periodic(function() {
                $time = time();
                foreach ($this->timeouts as $id => $timeout) {
                    if (0 < $timeout && $timeout <= $time) {
                        $socket = $this->readEvents[$id]->data;
                        $this->timeouts[$id] = $time + $socket->getTimeout();
                        $socket->onTimeout();
                    }
                }
            }, self::TIMEOUT_TIMER_INTERVAL);
            $this->timer->unreference();
        });
    }
    
    /**
     */
    public function __destruct()
    {
        if ($this->isRunning()) {
            $this->stop();
        }
        
        foreach ($this->readEvents as $event) {
            $event->stop();
        }
        
        foreach ($this->writeEvents as $event) {
            $event->stop();
        }
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->stop();
        }
        
        foreach ($this->signalEvents as $event) {
            $event->stop();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function reInit()
    {
        EvLoop::defaultLoop()->loopFork();
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
        $flags = Ev::RUN_ONCE;
        
        if (!$blocking) {
            $flags |= Ev::RUN_NOWAIT;
        }
        
        Ev::run($flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * {@inheritdoc}
     */
    public function addReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->readEvents[$id])) {
            $event = new EvIO($socket->getResource(), Ev::READ, $this->readCallback, $socket);
            
            $event->start();
            $this->readEvents[$id] = $event;
            
            if ($timeout = $socket->getTimeout()) {
                $this->timeouts[$id] = time() + $timeout;
            } else {
                $this->timeouts[$id] = 0;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function pauseReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvents[$id])) {
            $this->readEvents[$id]->stop();
            unset($this->timeouts[$id]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function resumeReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->readEvents[$id]) && !$this->readEvents[$id]->is_active) {
            $this->readEvents[$id]->start();
            
            if ($timeout = $socket->getTimeout()) {
                $this->timeouts[$id] = time() + $timeout;
            } else {
                $this->timeouts[$id] = 0;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadableSocketPending(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->readEvents[$id]) && $this->readEvents[$id]->is_active;
    }
    
    /**
     * {@inheritdoc}
     */
    public function scheduleWritableSocket(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->writeEvents[$id])) {
            $this->writeEvents[$id] = new EvIO($socket->getResource(), Ev::WRITE, $this->writeCallback, $socket);
        }
        
        $this->writeEvents[$id]->start();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritableSocketScheduled(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->writeEvents[$id]) && $this->writeEvents[$id]->is_active;
    }
    
    /**
     * {@inheritdoc}
     */
    public function unscheduleWritableSocket(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->writeEvents[$id])) {
            $this->writeEvents[$id]->stop();
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
            $this->readEvents[$id]->stop();
            unset($this->readEvents[$id]);
            unset($this->timeouts[$id]);
        }
        
        if (isset($this->writeEvents[$id])) {
            $this->writeEvents[$id]->stop();
            unset($this->writeEvents[$id]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function addTimer(TimerInterface $timer)
    {
        if (!isset($this->timers[$timer])) {
            $event = new EvTimer(
                $timer->getInterval(),
                $timer->isPeriodic() ? $timer->getInterval() : 0,
                $this->timerCallback,
                $timer
            );
            
            $this->timers[$timer] = $event;
            
            $event->start();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->stop();
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
            $event->stop();
        }
        
        foreach ($this->writeEvents as $event) {
            $event->stop();
        }
        
        $this->readEvents = [];
        $this->writeEvents = [];
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->stop();
        }
        
        $this->timers = new UnreferencableObjectStorage();
        
        if (null !== $this->timer) {
            $this->timer->start();
        }
    }
}
