<?php
namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;
use Icicle\Loop\Structures\UnreferencableObjectStorage;

class TimerManager implements TimerManagerInterface
{
    /**
     * @var EventBase
     */
    private $base;
    
    /**
     * @var EventFactoryInterface
     */
    private $factory;
    
    /**
     * UnreferencableObjectStorage mapping Timer objects to Event objects.
     *
     * @var UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   EventFactoryInterface $factory
     * @param   EventBase $base
     */
    public function __construct(EventFactoryInterface $factory, EventBase $base)
    {
        $this->factory = $factory;
        $this->base = $base;
        
        $this->timers = new UnreferencableObjectStorage();
        
        $this->callback = $this->createCallback();
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->free();
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        return !$this->timers->count();
    }
    
    /**
     * @inheritdoc
     */
    public function create(callable $callback, $interval, $periodic = false, array $args = null)
    {
        $timer = $this->factory->timer($this, $callback, $interval, $periodic, $args);
        
        $flags = Event::TIMEOUT;
        if ($timer->isPeriodic()) {
            $flags |= Event::PERSIST;
        }
        
        $event = new Event($this->base, -1, $flags, $this->callback, $timer);
        
        $this->timers[$timer] = $event;
        
        $event->add($timer->getInterval());
        
        return $timer;
    }
    
    /**
     * @inheritdoc
     */
    public function cancel(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->free();
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isPending(TimerInterface $timer)
    {
        return isset($this->timers[$timer]) && $this->timers[$timer]->pending;
    }
    
    /**
     * @inheritdoc
     */
    public function unreference(TimerInterface $timer)
    {
        $this->timers->unreference($timer);
    }
    
    /**
     * @inheritdoc
     */
    public function reference(TimerInterface $timer)
    {
        $this->timers->reference($timer);
    }
    
    /**
     * @inheritdoc
     */
    public function clear()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->free();
        }
        
        $this->timers = new UnreferencableObjectStorage();
    }
    
    /**
     * @return  callable
     */
    protected function createCallback()
    {
        return function ($resource, $what, TimerInterface $timer) {
            if (!$this->timers[$timer]->pending) {
                $this->timers[$timer]->free();
                unset($this->timers[$timer]);
            }
            
            $timer->call();
        };
    }
}