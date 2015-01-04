<?php
namespace Icicle\Loop;

use Event;
use EventBase;
use Icicle\Loop\Events\AwaitInterface;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\PollInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Exception\FreedException;
use Icicle\Loop\Exception\ResourceBusyException;
use Icicle\Loop\Exception\UnsupportedException;
use Icicle\Structures\UnreferencableObjectStorage;

class EventLoop extends AbstractLoop
{
    /**
     * @var EventBase
     */
    private $base;
    
    /**
     * @var PollInterface[int]
     */
    private $polls = [];
    
    /**
     * @var AwaitInterface[int]
     */
    private $awaits = [];
    
    /**
     * UnreferencableObjectStorage mapping Timer objects to Event objects.
     *
     * @var UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @var Event[int]
     */
    private $readEvents = [];
    
    /**
     * @var Event[int]
     */
    private $writeEvents = [];
    
    /**
     * @var Event[int]
     */
    private $signalEvents = [];
    
    /**
     * @var Closure
     */
    private $socketCallback;
    
    /**
     * @var Closure
     */
    private $timerCallback;
    
    /**
     * Determines if the event extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('event');
    }
    
    /**
     * @param   EventFactoryInterface|null $eventFactory
     *
     * @throws  UnsupportedException Thrown if the event extension is not loaded.
     */
    public function __construct(EventFactoryInterface $eventFactory = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedException('EventLoop class requires the event extension.');
        } // @codeCoverageIgnoreEnd
        
        parent::__construct($eventFactory);
        
        $this->base = new EventBase();
        $this->timers = new UnreferencableObjectStorage();
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $this->createEvent($signal);
                $event = new Event($this->base, $signal, Event::SIGNAL | Event::PERSIST, $callback);
                $event->add();
                $this->signalEvents[$signal] = $event;
            }
        }
        
        $this->socketCallback = function ($resource, $what, SocketEventInterface $event) {
            $event->call($resource, Event::TIMEOUT & $what);
        };
        
        $this->timerCallback = function ($_, $what, TimerInterface $timer) {
            if (!$this->timers[$timer]->pending(Event::TIMEOUT)) {
                $this->timers[$timer]->free();
                unset($this->timers[$timer]);
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
            $event->free();
        }
        
        foreach ($this->writeEvents as $event) {
            $event->free();
        }
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->free();
        }
        
        foreach ($this->signalEvents as $event) {
            $event->free();
        }
    }
    
    /**
     * Calls reInit() on the EventBase object.
     */
    public function reInit()
    {
        $this->base->reInit();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        foreach ($this->readEvents as $event) {
            if ($event->pending) {
                return false;
            }
        }
        
        foreach ($this->writeEvents as $event) {
            if ($event->pending) {
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
        $flags = EventBase::LOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EventBase::LOOP_NONBLOCK;
        }
        
        $this->base->loop($flags); // Dispatch I/O, timer, and signal callbacks.
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
            $this->readEvents[$id] = new Event($this->base, $poll->getResource(), Event::READ, $this->socketCallback, $poll);
        }
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            $this->readEvents[$id]->add($timeout);
        } else {
            $this->readEvents[$id]->add();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelPoll(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        if (isset($this->polls[$id], $this->readEvents[$id]) && $poll === $this->polls[$id]) {
            $this->readEvents[$id]->del();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPollPending(PollInterface $poll)
    {
        $id = (int) $poll->getResource();
        
        return isset($this->polls[$id], $this->readEvents[$id]) && $poll === $this->polls[$id] && $this->readEvents[$id]->pending;
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
                $this->readEvents[$id]->free();
                unset($this->readEvents[$id]);
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
            $this->writeEvents[$id] = new Event($this->base, $await->getResource(), Event::WRITE, $this->socketCallback, $await);
        }
        
        if (null !== $timeout) {
            $timeout = (float) $timeout;
            if (self::MIN_TIMEOUT > $timeout) {
                $timeout = self::MIN_TIMEOUT;
            }
            $this->writeEvents[$id]->add($timeout);
        } else {
            $this->writeEvents[$id]->add();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelAwait(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        if (isset($this->awaits[$id], $this->writeEvents[$id]) && $await === $this->awaits[$id]) {
            $this->writeEvents[$id]->del();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isAwaitPending(AwaitInterface $await)
    {
        $id = (int) $await->getResource();
        
        return isset($this->awaits[$id], $this->writeEvents[$id]) && $await === $this->awaits[$id] && $this->writeEvents[$id]->pending;
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
                $this->writeEvents[$id]->free();
                unset($this->writeEvents[$id]);
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
        
        $flags = Event::TIMEOUT;
        if ($timer->isPeriodic()) {
            $flags |= Event::PERSIST;
        }
        
        $event = new Event($this->base, -1, $flags, $this->timerCallback, $timer);
        
        $this->timers[$timer] = $event;
        
        $event->add($timer->getInterval());
        
        return $timer;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelTimer(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->free();
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isTimerPending(TimerInterface $timer)
    {
        return isset($this->timers[$timer]) && $this->timers[$timer]->pending;
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
            $event->free();
        }
        
        foreach ($this->writeEvents as $event) {
            $event->free();
        }
        
        $this->polls = [];
        $this->awaits = [];
        $this->readEvents = [];
        $this->writeEvents = [];
        
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->free();
        }
        
        $this->timers = new UnreferencableObjectStorage();
    }
}
