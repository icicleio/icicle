<?php
namespace Icicle\Loop;

use Icicle\Loop\Exception\RunningException;
use Icicle\Socket\ReadableSocketInterface;
use Icicle\Socket\SocketInterface;
use Icicle\Socket\WritableSocketInterface;
use Icicle\Timer\Timer;
use Icicle\Timer\TimerInterface;
use Icicle\Timer\TimerQueue;

class SelectLoop extends AbstractLoop
{
    const TIMEOUT_TIMER_INTERVAL = 1;
    const MICROSEC_PER_SEC = 1e6;
    const NANOSEC_PER_SEC = 1e9;
    
    /**
     * Array of ReadableSocketInterface objects waiting to read.
     *
     * @var     ReadableSocketInterface[int]
     */
    private $sockets = [];
    
    /**
     * Array of ReadableSocketInterface objects that are paused.
     *
     * @var     ReadableSocketInterface[int]
     */
    private $paused = [];
    
    /**
     * Array of timestamps of last read times.
     *
     * @var     int[]
     */
    private $timeouts = [];
    
    /**
     * Array of WritableSocketInterface objects waiting to write.
     *
     * @var     WritableSocketInterface[int]
     */
    private $queue = [];
    
    /**
     * @var     TimerQueue
     */
    private $timerQueue;
    
    /**
     * @var     bool
     */
    private $running = false;
    
    /**
     * @var     Timer|null
     */
    private $timer;
    
    /**
     * Always returns true for this class, since this class only requires core PHP functions.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return true;
    }
    
    /**
     * @param   Logger|null $logger
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->timerQueue = new TimerQueue();
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                pcntl_signal($signal, $callback);
            }
        }
        
        $this->schedule(function () {
            $this->timer = Timer::periodic(function() {
                $time = time();
                foreach ($this->timeouts as $id => $timeout) {
                    if (0 < $timeout && $timeout <= $time) {
                        $this->timeouts[$id] = $time + $this->sockets[$id]->getTimeout();
                        $this->sockets[$id]->onTimeout();
                    }
                }
            }, self::TIMEOUT_TIMER_INTERVAL);
            $this->timer->unreference();
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function reInit() { /* Nothing to be done after fork. */ }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        if ($blocking) {
            $timeout = $this->timerQueue->getTimeout(); // There should always be at least one timer.
        } else {
            $timeout = 0;
        }
        
        $seconds = floor($timeout);
        $remainder = ($timeout - $seconds);
        
        $read = [];
        $write = [];
        $except = null;
        
        foreach ($this->sockets as $socket) {
            $read[] = $socket->getResource();
        }
        
        foreach ($this->queue as $socket) {
            $write[] = $socket->getResource();
        }
        
        if (count($read) || count($write)) { // Use stream_select() if there are any streams in the loop.
            // Error reporting supressed since stream_select() emits an E_WARNING if it is interrupted by a signal. *sigh*
            $count = @stream_select($read, $write, $except, $seconds, $remainder * self::MICROSEC_PER_SEC);
            
            if ($count) {
                $time = time();
                
                foreach ($read as $resource) {
                    $id = (int) $resource;
                    if (isset($this->sockets[$id])) { // Connection may have been removed from a previous call.
                        if (isset($this->timeouts[$id]) && 0 < $this->timeouts[$id]) {
                            $this->timeouts[$id] = $time + $this->sockets[$id]->getTimeout();
                        }
                        $this->sockets[$id]->onRead();
                    }
                }
                
                foreach ($write as $resource) {
                    $id = (int) $resource;
                    if (isset($this->queue[$id])) { // Connection may have been removed from a previous call.
                        $socket = $this->queue[$id];
                        unset($this->queue[$id]); // Remove connection from the queue since it was able to write.
                        $socket->onWrite();
                    }
                }
            }
        } elseif (0 < $timeout) { // Use time_nanosleep() if the loop only contains timers.
            time_nanosleep($seconds, $remainder * self::NANOSEC_PER_SEC); // Will be interrupted if a signal is received.
        }
        
        if ($this->signalHandlingEnabled()) {
            pcntl_signal_dispatch(); // Dispatch any signals that may have arrived.
        }
        
        $this->timerQueue->tick(); // Call any pending timers.
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->sockets) && empty($this->queue) && !$this->timerQueue->count() && parent::isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function addReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->sockets[$id]) && !isset($this->paused[$id])) {
            $this->sockets[$id] = $socket;
            if ($timeout = $socket->getTimeout()) {
                $this->timeouts[$id] = time() + $timeout;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function pauseReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->sockets[$id])) {
            unset($this->sockets[$id]);
            unset($this->timeouts[$id]);
            $this->paused[$id] = $socket;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function resumeReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (isset($this->paused[$id])) {
            unset($this->paused[$id]);
            $this->sockets[$id] = $socket;
            if ($timeout = $socket->getTimeout()) {
                $this->timeouts[$id] = time() + $timeout;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadableSocketPending(ReadableSocketInterface $socket)
    {
        return isset($this->sockets[$socket->getId()]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function scheduleWritableSocket(WritableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->queue[$id])) {
            $this->queue[$id] = $socket;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritableSocketScheduled(WritableSocketInterface $socket)
    {
        return isset($this->queue[$socket->getId()]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function unscheduleWritableSocket(WritableSocketInterface $socket)
    {
        unset($this->queue[$socket->getId()]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function containsSocket(SocketInterface $socket)
    {
        $id = $socket->getId();
        
        return isset($this->socket[$id]) || isset($this->paused[$id]) || isset($this->queue[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeSocket(SocketInterface $socket)
    {
        $id = $socket->getId();
        
        unset($this->sockets[$id]);
        unset($this->paused[$id]);
        unset($this->timeouts[$id]);
        unset($this->queue[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function addTimer(TimerInterface $timer)
    {
        $this->timerQueue->add($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        $this->timerQueue->remove($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isTimerActive(TimerInterface $timer)
    {
        return $this->timerQueue->contains($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function unreferenceTimer(TimerInterface $timer)
    {
        $this->timerQueue->unreference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function referenceTimer(TimerInterface $timer)
    {
        $this->timerQueue->reference($timer);
    }
    
    public function clear()
    {
        parent::clear();
        
        $this->sockets = [];
        $this->paused = [];
        $this->timeouts = [];
        $this->queue = [];
        
        $this->timerQueue->clear();
        
        if (null !== $this->timer) {
            $this->timer->start();
        }
    }
}
