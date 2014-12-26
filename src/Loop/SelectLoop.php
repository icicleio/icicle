<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\Await;
use Icicle\Loop\Events\Poll;
use Icicle\Loop\Events\Timer;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\InvalidArgumentException;
use Icicle\Loop\Structures\TimerQueue;

class SelectLoop extends AbstractLoop
{
    const INTERVAL = 0.1;
    
    const MICROSEC_PER_SEC = 1e6;
    const NANOSEC_PER_SEC = 1e9;
    
    /**
     * @var Poll[int]
     */
    private $polls = [];
    
    /**
     * @var resource[int]
     */
    private $read = [];
    
    /**
     * @var float[int]
     */
    private $timeouts = [];
    
    /**
     * @var Await[int]
     */
    private $awaits = [];
    
    /**
     * @var resource[int]
     */
    private $write = [];
    
    /**
     * @var TimerQueue
     */
    private $timerQueue;
    
    /**
     * @var Timer
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
     */
    public function __construct($interval = self::INTERVAL)
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
        
        $this->timer = $this->createTimer($interval, true, function () {
            $time = microtime(true);
            foreach ($this->timeouts as $id => $timeout) { // Look for sockets that have timed out.
                if ($timeout <= $time && isset($this->polls[$id])) {
                    $poll = $this->polls[$id];
                    unset($this->read[$id]);
                    unset($this->timeouts[$id]);
                    
                    $callback = $poll->getCallback();
                    $callback($poll->getResource(), true);
                }
            }
        });
        
        $this->timer->unreference();
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
            $timeout = $this->timerQueue->getInterval();
        } else {
            $timeout = 0;
        }
        
        $this->select($timeout); // Select available sockets for reading or writing.
        
        $this->timerQueue->tick(); // Call any pending timers.
        
        if ($this->signalHandlingEnabled()) {
            pcntl_signal_dispatch(); // Dispatch any signals that may have arrived.
        }
    }
    
    /**
     * @param   float $timeout
     *
     * @return  bool
     */
    protected function select($timeout)
    {
        if (count($this->read) || count($this->write)) { // Use stream_select() if there are any streams in the loop.
            $seconds = (int) floor($timeout);
            $microseconds = ($timeout - $seconds) * self::MICROSEC_PER_SEC;
            
            $read = [];
            $write = [];
            $except = null;
            
            foreach ($this->read as $id => $resource) {
                $read[$id] = $resource;
            }
            
            foreach ($this->write as $id => $resource) {
                $write[$id] = $resource;
            }
            
            // Error reporting suppressed since stream_select() emits an E_WARNING if it is interrupted by a signal. *sigh*
            $count = @stream_select($read, $write, $except, $seconds, $microseconds);
            
            if ($count) {
                foreach ($read as $id => $resource) {
                    if (isset($this->polls[$id], $this->read[$id])) { // Socket may have been removed from a previous call.
                        $poll = $this->polls[$id];
                        unset($this->read[$id]);
                        unset($this->timeouts[$id]);
                        
                        $callback = $poll->getCallback();
                        $callback($resource, false);
                    }
                }
                
                foreach ($write as $id => $resource) {
                    if (isset($this->awaits[$id], $this->write[$id])) { // Socket may have been removed from a previous call.
                        $await = $this->awaits[$id];
                        unset($this->write[$id]);
                        
                        $callback = $await->getCallback();
                        $callback($resource);
                    }
                }
            }
        } elseif (0 !== $timeout) { // Otherwise sleep with time_nanosleep() if $timeout > 0.
            $seconds = (int) floor($timeout);
            $nanoseconds = ($timeout - $seconds) * self::NANOSEC_PER_SEC;
        
            time_nanosleep($seconds, $nanoseconds); // Will be interrupted if a signal is received.
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return empty($this->read) && empty($this->write) && !$this->timerQueue->count() && parent::isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function createPoll($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (!isset($this->polls[$id])) {
            $this->polls[$id] = new Poll($this, $resource, $callback);
        } else {
            $this->polls[$id]->set($callback);
        }
        
        return $this->polls[$id];
        
/*
        $poll = new Poll($this, $resource, $callback);
        
        $this->polls[(int) $resource] = $poll;
        
        return $poll;
*/
    }
    
    public function addPoll(Poll $poll, $timeout = null)
    {
        $resource = $poll->getResource();
        $id = (int) $resource;
        
        if (!isset($this->polls[$id]) || $poll !== $this->polls[$id]) {
            throw new FreedException('Poll has been freed.');
        }
        
        $this->read[$id] = $resource;
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            
            $this->timeouts[$id] = microtime(true) + $timeout;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelPoll(Poll $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id]) && $poll === $this->polls[$id]) {
            unset($this->read[$id]);
            unset($this->timeouts[$id]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPollPending(Poll $poll)
    {
        $id = (int) $poll->getResource();
        
        return isset($this->polls[$id]) && $poll === $this->polls[$id] && isset($this->read[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function freePoll(Poll $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id]) && $poll === $this->polls[$id]) {
            unset($this->polls[$id]);
            unset($this->read[$id]);
            unset($this->timeouts[$id]);
        }
    }
    
    public function isPollFreed(Poll $poll)
    {
        $id = (int) $poll->getResource();
        
        return isset($this->polls[$id]) && $poll === $this->polls[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function createAwait($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (!isset($this->awaits[$id])) {
            $this->awaits[$id] = new Await($this, $resource, $callback);
        } else {
            $this->awaits[$id]->set($callback);
        }
        
        return $this->awaits[$id];
/*
        $await = new Await($this, $resource, $callback);
        
        $this->awaits[(int) $resource] = $await;
        
        return $await;
*/
    }
    
    public function addAwait(Await $await, $timeout = null)
    {
        $resource = $await->getResource();
        $id = (int) $resource;
        
        if (!isset($this->awaits[$id]) || $await !== $this->awaits[$id]) {
            throw new FreedException('Await has been freed.');
        }
        
        $this->write[$id] = $resource;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelAwait(Await $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->write[$id]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAwaitPending(Await $await)
    {
        $id = (int) $await->getResource();
        
        return isset($this->awaits[$id]) && $await === $this->awaits[$id] && isset($this->write[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function freeAwait(Await $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->awaits[$id]);
            unset($this->write[$id]);
        }
    }
    
    public function isAwaitFreed(Await $await)
    {
        $id = (int) $await->getResource();
        
        return isset($this->awaits[$id]) && $await === $this->awaits[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTimer($interval, $periodic, callable $callback, array $args = [])
    {
        $timer = new Timer($this, $interval, $periodic, $callback, $args);
        
        $this->timerQueue->add($timer);
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(Timer $timer)
    {
        $this->timerQueue->remove($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isTimerPending(Timer $timer)
    {
        return $this->timerQueue->contains($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function unreferenceTimer(Timer $timer)
    {
        $this->timerQueue->unreference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function referenceTimer(Timer $timer)
    {
        $this->timerQueue->reference($timer);
    }
    
    public function clear()
    {
        parent::clear();
        
        $this->polls = [];
        $this->read = [];
        $this->timeouts = [];
        
        $this->awaits = [];
        $this->write = [];
        
        $this->timerQueue->clear();
        
        $this->timerQueue->add($this->timer);
        $this->timerQueue->unreference($this->timer);
    }
}
