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
    const INTERVAL = 1;
    
    const MICROSEC_PER_SEC = 1e6;
    const NANOSEC_PER_SEC = 1e9;
    
    /**
     * Array of ReadableSocketInterface objects waiting to read.
     *
     * @var ReadableSocketInterface[int]
     */
    private $sockets = [];
    
    /**
     * Array of timestamps of last read times.
     *
     * @var float[]
     */
    private $timeouts = [];
    
    /**
     * Array of WritableSocketInterface objects waiting to write.
     *
     * @var WritableSocketInterface[int]
     */
    private $queue = [];
    
    /**
     * @var TimerQueue
     */
    private $timerQueue;
    
    /**
     * @var float
     */
    private $last;
    
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
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->timerQueue = new TimerQueue();
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $this->createEvent($signal);
                pcntl_signal($signal, $callback);
            }
        }
        
        $this->last = microtime(true);
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
            $timeout = self::INTERVAL;
            
            if (false !== ($interval = $this->timerQueue->getInterval()) && $interval < $timeout) {
                $timeout = $interval;
            }
        } else {
            $timeout = 0;
        }
/*
        if (!$blocking) {
            $timeout = 0;
        } else {
            $timeout = self::INTERVAL - (microtime(true) - $this->last);
            if (0 > $timeout) {
                $timeout = 0;
            }
        }
        
        if (0 !== $timeout && false !== ($interval = $this->timerQueue->getInterval()) && $interval < $timeout) {
            $timeout = $interval;
        }
*/
        
        if (!$this->select($timeout) && 0 !== $timeout) { // Select available sockets for reading or writing.
            $this->sleep($timeout); // If no sockets available, sleep until the next timer is ready.
        }
        
        $this->timerQueue->tick(); // Call any pending timers.
        
        if ($this->signalHandlingEnabled()) {
            pcntl_signal_dispatch(); // Dispatch any signals that may have arrived.
        }
        
        $this->socketTimeoutDispatch(); // Call onTimeout on any sockets that have timed out.
    }
    
    /**
     * @param   float $timeout
     *
     * @return  bool
     */
    protected function select($timeout)
    {
        if (count($this->sockets) || count($this->queue)) { // Use stream_select() if there are any streams in the loop.
            $seconds = (int) floor($timeout);
            $microseconds = ($timeout - $seconds) * self::MICROSEC_PER_SEC;
            
            $read = [];
            $write = [];
            $except = null;
            
            foreach ($this->sockets as $id => $socket) {
                $read[$id] = $socket->getResource();
            }
            
            foreach ($this->queue as $id => $socket) {
                $write[$id] = $socket->getResource();
            }
            
            // Error reporting suppressed since stream_select() emits an E_WARNING if it is interrupted by a signal. *sigh*
            $count = @stream_select($read, $write, $except, $seconds, $microseconds);
            
            if ($count) {
                foreach ($read as $id => $resource) {
                    if (isset($this->sockets[$id])) { // Socket may have been removed from a previous call.
                        $socket = $this->sockets[$id];
                        unset($this->sockets[$id]);
                        unset($this->timeouts[$id]);
                        
                        $socket->onRead();
                    }
                }
                
                foreach ($write as $id => $resource) {
                    if (isset($this->queue[$id])) { // Socket may have been removed from a previous call.
                        $socket = $this->queue[$id];
                        unset($this->queue[$id]);
                        
                        $socket->onWrite();
                    }
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * @param   float $timeout
     *
     * @return  bool
     */
    protected function sleep($timeout)
    {
        $seconds = (int) floor($timeout);
        $nanoseconds = ($timeout - $seconds) * self::NANOSEC_PER_SEC;
        
        return true === time_nanosleep($seconds, $nanoseconds); // Will be interrupted if a signal is received.
    }
    
    /**
     * Checks if any sockets have timed out.
     */
    protected function socketTimeoutDispatch()
    {
        $time = microtime(true);
        
/*
        if ($this->last <= $time - self::INTERVAL) { // Only execute every INTERVAL seconds.
            $this->last = $time;
            foreach ($this->timeouts as $id => $timeout) { // Look for sockets that have timed out.
                if ($timeout <= $time && isset($this->sockets[$id])) {
                    $socket = $this->sockets[$id];
                    unset($this->sockets[$id]);
                    unset($this->timeouts[$id]);
                    
                    $socket->onTimeout();
                }
            }
        }
*/
        foreach ($this->timeouts as $id => $timeout) { // Look for sockets that have timed out.
            if ($timeout <= $time && isset($this->sockets[$id])) {
                $socket = $this->sockets[$id];
                unset($this->sockets[$id]);
                unset($this->timeouts[$id]);
                
                $socket->onTimeout();
            }
        }
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
    public function scheduleReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        if (!isset($this->sockets[$id])) {
            $this->sockets[$id] = $socket;
            if ($timeout = $socket->getTimeout()) {
                $this->timeouts[$id] = microtime(true) + $timeout;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function unscheduleReadableSocket(ReadableSocketInterface $socket)
    {
        $id = $socket->getId();
        
        unset($this->sockets[$id]);
        unset($this->timeouts[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadableSocketScheduled(ReadableSocketInterface $socket)
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
    public function removeSocket(SocketInterface $socket)
    {
        $id = $socket->getId();
        
        unset($this->sockets[$id]);
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
    public function isTimerPending(TimerInterface $timer)
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
        $this->timeouts = [];
        $this->queue = [];
        
        $this->timerQueue->clear();
    }
}
