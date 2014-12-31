<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\AwaitInterface;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\PollInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\InvalidArgumentException;
use Icicle\Loop\Structures\TimerQueue;

class SelectLoop extends AbstractLoop
{
    const INTERVAL = 0.1;
    
    const MICROSEC_PER_SEC = 1e6;
    const NANOSEC_PER_SEC = 1e9;
    
    /**
     * @var PollInterface[int]
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
     * @var AwaitInterface[int]
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
     * @var TimerInterface
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
     * @param   EventFactoryInterface|null $eventFactory
     * @param   int|float $interval Interval between checking for timed-out sockets.
     */
    public function __construct(EventFactoryInterface $eventFactory = null, $interval = self::INTERVAL)
    {
        parent::__construct($eventFactory);
        
        $this->timerQueue = new TimerQueue();
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $this->createEvent($signal);
                pcntl_signal($signal, $callback);
            }
        }
        
        $this->timer = $this->createTimer(function () {
            $time = microtime(true);
            foreach ($this->timeouts as $id => $timeout) { // Look for sockets that have timed out.
                if ($timeout <= $time && isset($this->polls[$id])) {
                    unset($this->read[$id]);
                    unset($this->timeouts[$id]);
                    
                    $callback = $this->polls[$id]->getCallback();
                    $callback($this->polls[$id]->getResource(), true);
                }
            }
        }, $interval, true);
        
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
                    if (isset($this->polls[$id], $this->read[$id])) { // Poll may have been removed from a previous call.
                        unset($this->read[$id]);
                        unset($this->timeouts[$id]);
                        
                        $callback = $this->polls[$id]->getCallback();
                        $callback($resource, false);
                    }
                }
                
                foreach ($write as $id => $resource) {
                    if (isset($this->awaits[$id], $this->write[$id])) { // Await may have been removed from a previous call.
                        unset($this->write[$id]);
                        
                        $callback = $this->awaits[$id]->getCallback();
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
        
        if (isset($this->polls[$id])) {
            throw new LogicException('A poll has already been created for this resource.');
        }
        
        return $this->polls[$id] = $this->getEventFactory()->createPoll($this, $resource, $callback);
        
/*
        if (!isset($this->polls[$id])) {
            $this->polls[$id] = $this->getEventFactory()->createPoll($this, $resource, $callback);
        } else {
            $this->polls[$id]->set($callback);
        }
        
        return $this->polls[$id];
*/
    }
    
    public function listenPoll(PollInterface $poll, $timeout = null)
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
    public function cancelPoll(PollInterface $poll)
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
    public function isPollPending(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        return isset($this->polls[$id]) && $poll === $this->polls[$id] && isset($this->read[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function freePoll(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id]) && $poll === $this->polls[$id]) {
            unset($this->polls[$id]);
            unset($this->read[$id]);
            unset($this->timeouts[$id]);
        }
    }
    
    public function isPollFreed(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        return !isset($this->polls[$id]) || $poll !== $this->polls[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function createAwait($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->awaits[$id])) {
            throw new LogicException('An await has already been created for this resource.');
        }
        
        return $this->awaits[$id] = $this->getEventFactory()->createAwait($this, $resource, $callback);
        
/*
        if (!isset($this->awaits[$id])) {
            $this->awaits[$id] = $this->getEventFactory()->createAwait($this, $resource, $callback);
        } else {
            $this->awaits[$id]->set($callback);
        }
        
        return $this->awaits[$id];
*/
    }
    
    public function listenAwait(AwaitInterface $await, $timeout = null)
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
    public function cancelAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->write[$id]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAwaitPending(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return isset($this->awaits[$id]) && $await === $this->awaits[$id] && isset($this->write[$id]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function freeAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->awaits[$id]);
            unset($this->write[$id]);
        }
    }
    
    public function isAwaitFreed(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return !isset($this->awaits[$id]) || $await !== $this->awaits[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTimer(callable $callback, $interval, $periodic = false, array $args = [])
    {
        $timer = $this->getEventFactory()->createTimer($this, $callback, $interval, $periodic, $args);
        
        $this->timerQueue->add($timer);
        
        return $timer;
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
