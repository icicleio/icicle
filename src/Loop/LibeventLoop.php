<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\AwaitInterface;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\PollInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\ResourceBusyException;
use Icicle\Loop\Exception\UnsupportedException;
use Icicle\Structures\UnreferencableObjectStorage;

class LibeventLoop extends AbstractLoop
{
    const MICROSEC_PER_SEC = 1e6;
    
    /**
     * Event base created with event_base_new().
     *
     * @var resource
     */
    private $base;
    
    /**
     * UnreferencableObjectStorage mapping Timer objects to event resoures.
     *
     * @var UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @var PollInterface[int]
     */
    private $polls = [];
    
    /**
     * @var AwaitInterface[int]
     */
    private $awaits = [];
    
    /**
     * @var resource[int]
     */
    private $readEvents = [];
    
    /**
     * @var resource[int]
     */
    private $writeEvents = [];
    
    /**
     * @var resource[int]
     */
    private $signalEvents = [];
    
    /**
     * @var int[int]
     */
    private $pending = [];
    
    /**
     * @var Closure
     */
    private $readCallback;
    
    /**
     * @var Closure
     */
    private $writeCallback;
    
    /**
     * @var Closure
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
     * @param   EventFactoryInterface|null $eventFactory
     *
     * @throws  UnsupportedException Thrown if the libevent extension is not loaded.
     */
    public function __construct(EventFactoryInterface $eventFactory = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedException('LibeventLoop class requires the libevent extension.');
        } // @codeCoverageIgnoreEnd
        
        parent::__construct($eventFactory);
        
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
        
        $this->readCallback = function ($resource, $what, PollInterface $poll) {
            $this->pending[(int) $resource] &= ~EV_READ;
            $poll->call($resource, 0 !== (EV_TIMEOUT & $what));
        };
        
        $this->writeCallback = function ($resource, $what, AwaitInterface $await) {
            $this->pending[(int) $resource] &= ~EV_WRITE;
            $await->call($resource, 0 !== (EV_TIMEOUT & $what));
        };
        
        $this->timerCallback = function ($resource, $what, TimerInterface $timer) {
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
    public function createPoll($resource, callable $callback)
    {
        $id = (int) $resource;
        
        if (isset($this->polls[$id])) {
            throw new ResourceBusyException('A poll has already been created for that resource.');
        }
        
        return $this->polls[$id] = $this->getEventFactory()->createPoll($this, $resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function listenPoll(PollInterface $poll, $timeout = null)
    {
        $id = (int) $poll->getResource();
        
        if (!isset($this->polls[$id]) || $poll !== $this->polls[$id]) {
            throw new FreedException('Poll has been freed.');
        }
        
        if (!isset($this->readEvents[$id])) {
            $resource = $poll->getResource();
            $event = event_new();
            event_set($event, $poll->getResource(), EV_READ, $this->readCallback, $poll);
            event_base_set($event, $this->base);
            
            $this->readEvents[$id] = $event;
        }
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            event_add($this->readEvents[$id], $timeout * self::MICROSEC_PER_SEC);
        } else {
            event_add($this->readEvents[$id]);
        }
        
        if (!isset($this->pending[$id])) {
            $this->pending[$id] = EV_READ;
        } else {
            $this->pending[$id] |= EV_READ;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelPoll(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id], $this->readEvents[$id]) && $poll === $this->polls[$id]) {
            event_del($this->readEvents[$id]);
            $this->pending[$id] &= ~EV_READ;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPollPending(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        return isset($this->polls[$id], $this->pending[$id]) && $poll === $this->polls[$id] && EV_READ & $this->pending[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function freePoll(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id]) && $poll === $this->polls[$id]) {
            unset($this->polls[$id]);
            
            if (isset($this->readEvents[$id])) {
                event_free($this->readEvents[$id]);
                unset($this->readEvents[$id]);
                
                if (isset($this->pending[$id])) {
                    $this->pending[$id] &= ~EV_READ;
                    if (0 === $this->pending[$id]) {
                        unset($this->pending[$id]);
                    }
                }
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
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
            throw new ResourceBusyException('An await has already been created for that resource.');
        }
        
        return $this->awaits[$id] = $this->getEventFactory()->createAwait($this, $resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function listenAwait(AwaitInterface $await, $timeout = null)
    {
        $id = (int) $await->getResource();
        
        if (!isset($this->awaits[$id]) || $await !== $this->awaits[$id]) {
            throw new FreedException('Await has been freed.');
        }
        
        if (!isset($this->writeEvents[$id])) {
            $event = event_new();
            event_set($event, $await->getResource(), EV_WRITE, $this->writeCallback, $await);
            event_base_set($event, $this->base);
            
            $this->writeEvents[$id] = $event;
        }
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            event_add($this->writeEvents[$id], $timeout * self::MICROSEC_PER_SEC);
        } else {
            event_add($this->writeEvents[$id]);
        }
        
        if (!isset($this->pending[$id])) {
            $this->pending[$id] = EV_WRITE;
        } else {
            $this->pending[$id] |= EV_WRITE;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id], $this->writeEvents[$id]) && $await === $this->awaits[$id]) {
            event_del($this->writeEvents[$id]);
            $this->pending[$id] &= ~EV_WRITE;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAwaitPending(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return isset($this->awaits[$id], $this->pending[$id]) && $await === $this->awaits[$id] && EV_WRITE & $this->pending[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function freeAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id]) && $await === $this->awaits[$id]) {
            unset($this->awaits[$id]);
            
            if (isset($this->writeEvents[$id])) {
                event_free($this->writeEvents[$id]);
                unset($this->writeEvents[$id]);
                
                if (isset($this->pending[$id])) {
                    $this->pending[$id] &= ~EV_WRITE;
                    if (0 === $this->pending[$id]) {
                        unset($this->pending[$id]);
                    }
                }
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAwaitFreed(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return !isset($this->awaits[$id]) || $await !== $this->awaits[$id];
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTimer(callable $callback, $interval, $periodic = false, array $args = null)
    {
        $timer = $this->getEventFactory()->createTimer($this, $callback, $interval, $periodic, $args);
        
        $event = event_new();
        event_timer_set($event, $this->timerCallback, $timer);
        event_base_set($event, $this->base);
        
        $this->timers[$timer] = $event;
        
        event_add($event, $timer->getInterval() * self::MICROSEC_PER_SEC);
        
        return $timer;
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
        
        $this->polls = [];
        $this->awaits = [];
        $this->readEvents = [];
        $this->writeEvents = [];
        $this->pending = [];
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            event_free($this->timers->getInfo());
        }
        
        $this->timers = new UnreferencableObjectStorage();
    }
}
