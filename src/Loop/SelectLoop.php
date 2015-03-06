<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\AwaitInterface;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\PollInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\InvalidArgumentException;
use Icicle\Loop\Exception\ResourceBusyException;
use Icicle\Loop\Structures\TimerQueue;

class SelectLoop extends AbstractLoop
{
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
     * @var TimerInterface[int]
     */
    private $pollTimers = [];
    
    /**
     * @var AwaitInterface[int]
     */
    private $awaits = [];
    
    /**
     * @var resource[int]
     */
    private $write = [];
    
    /**
     * @var TimerInterface[int]
     */
    private $awaitTimers = [];
    
    /**
     * @var TimerQueue
     */
    private $timerQueue;
    
    /**
     * @var Closure
     */
    private $pollTimerCallback;
    
    /**
     * @var Closure
     */
    private $awaitTimerCallback;
    
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
     */
    public function __construct(EventFactoryInterface $eventFactory = null)
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
        
        $this->pollTimerCallback = function (PollInterface $poll) {
            $resource = $poll->getResource();
            $id = (int) $resource;
            unset($this->read[$id]);
            unset($this->pollTimers[$id]);
            
            $poll->call($resource, true);
        };
        
        $this->awaitTimerCallback = function (AwaitInterface $await) {
            $resource = $await->getResource();
            $id = (int) $resource;
            unset($this->write[$id]);
            unset($this->awaitTimers[$id]);
            
            $await->call($resource, true);
        };
    }
    
    /**
     * @inheritdoc
     */
    public function reInit() { /* Nothing to be done after fork. */ }
    
    /**
     * @inheritdoc
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
     * @param   int|float|null $timeout
     *
     * @return  bool
     */
    protected function select($timeout)
    {
        if ($this->read || $this->write) { // Use stream_select() if there are any streams in the loop.
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
            $count = @stream_select($read, $write, $except, null === $timeout ? null : $seconds, $microseconds);
            
            if ($count) {
                foreach ($read as $id => $resource) {
                    if (isset($this->polls[$id], $this->read[$id])) { // Poll may have been removed from a previous call.
                        unset($this->read[$id]);
                        
                        if (isset($this->pollTimers[$id])) {
                            $this->pollTimers[$id]->cancel();
                            unset($this->pollTimers[$id]);
                        }
                        
                        $this->polls[$id]->call($resource, false);
                    }
                }
                
                foreach ($write as $id => $resource) {
                    if (isset($this->awaits[$id], $this->write[$id])) { // Await may have been removed from a previous call.
                        unset($this->write[$id]);
                        
                        if (isset($this->awaitTimers[$id])) {
                            $this->awaitTimers[$id]->cancel();
                            unset($this->awaitTimers[$id]);
                        }
                        
                        $this->awaits[$id]->call($resource, false);
                    }
                }
            }
        } elseif (0 < $timeout) { // Otherwise sleep with time_nanosleep() if $timeout > 0.
            $seconds = (int) floor($timeout);
            $nanoseconds = ($timeout - $seconds) * self::NANOSEC_PER_SEC;
        
            time_nanosleep($seconds, $nanoseconds); // Will be interrupted if a signal is received.
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        return empty($this->read) && empty($this->write) && !$this->timerQueue->count() && parent::isEmpty();
    }
    
    /**
     * @inheritdoc
     */
    public function createPoll($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->polls[$id])) {
            throw new ResourceBusyException('A poll has already been created for this resource.');
        }
        
        return $this->polls[$id] = $this->getEventFactory()->createPoll($this, $resource, $callback);
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
            
            $this->pollTimers[$id] = $this->createTimer($this->pollTimerCallback, $timeout, false, [$poll]);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function cancelPoll(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id]) && $poll === $this->polls[$id]) {
            unset($this->read[$id]);
            
            if (isset($this->pollTimers[$id])) {
                $this->pollTimers[$id]->cancel();
                unset($this->pollTimers[$id]);
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isPollPending(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        return isset($this->polls[$id]) && $poll === $this->polls[$id] && isset($this->read[$id]);
    }
    
    /**
     * @inheritdoc
     */
    public function freePoll(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id]) && $poll === $this->polls[$id]) {
            unset($this->polls[$id]);
            unset($this->read[$id]);
            
            if (isset($this->pollTimers[$id])) {
                $this->pollTimers[$id]->cancel();
                unset($this->pollTimers[$id]);
            }
        }
    }
    
    public function isPollFreed(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        return !isset($this->polls[$id]) || $poll !== $this->polls[$id];
    }
    
    /**
     * @inheritdoc
     */
    public function createAwait($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->awaits[$id])) {
            throw new ResourceBusyException('An await has already been created for this resource.');
        }
        
        return $this->awaits[$id] = $this->getEventFactory()->createAwait($this, $resource, $callback);
    }
    
    public function listenAwait(AwaitInterface $await, $timeout = null)
    {
        $resource = $await->getResource();
        $id = (int) $resource;
        
        if (!isset($this->awaits[$id]) || $await !== $this->awaits[$id]) {
            throw new FreedException('Await has been freed.');
        }
        
        $this->write[$id] = $resource;
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            
            $this->awaitTimers[$id] = $this->createTimer($this->awaitTimerCallback, $timeout, false, [$await]);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function cancelAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->write[$id]);
            
            if (isset($this->awaitTimers[$id])) {
                $this->awaitTimers[$id]->cancel();
                unset($this->awaitTimers[$id]);
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isAwaitPending(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return isset($this->awaits[$id]) && $await === $this->awaits[$id] && isset($this->write[$id]);
    }
    
    /**
     * @inheritdoc
     */
    public function freeAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->awaits[$id]);
            unset($this->write[$id]);
            
            if (isset($this->awaitTimers[$id])) {
                $this->awaitTimers[$id]->cancel();
                unset($this->awaitTimers[$id]);
            }
        }
    }
    
    public function isAwaitFreed(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return !isset($this->awaits[$id]) || $await !== $this->awaits[$id];
    }
    
    /**
     * @inheritdoc
     */
    public function createTimer(callable $callback, $interval, $periodic = false, array $args = null)
    {
        $timer = $this->getEventFactory()->createTimer($this, $callback, $interval, $periodic, $args);
        
        $this->timerQueue->add($timer);
        
        return $timer;
    }
    
    /**
     * @inheritdoc
     */
    public function cancelTimer(TimerInterface $timer)
    {
        $this->timerQueue->remove($timer);
    }
    
    /**
     * @inheritdoc
     */
    public function isTimerPending(TimerInterface $timer)
    {
        return $this->timerQueue->contains($timer);
    }
    
    /**
     * @inheritdoc
     */
    public function unreferenceTimer(TimerInterface $timer)
    {
        $this->timerQueue->unreference($timer);
    }
    
    /**
     * @inheritdoc
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
        $this->pollTimers = [];
        
        $this->awaits = [];
        $this->write = [];
        $this->awaitTimers = [];
        
        $this->timerQueue->clear();
    }
}
