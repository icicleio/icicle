<?php
namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Structures\UnreferencableObjectStorage;
use Icicle\Loop\Manager\TimerManagerInterface;

class TimerManager implements TimerManagerInterface
{
    const MICROSEC_PER_SEC = 1e6;
    
    /**
     * @var \EventBase
     */
    private $base;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * UnreferencableObjectStorage mapping Timer objects to Event objects.
     *
     * @var \Icicle\Loop\Structures\UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param   resource $base
     */
    public function __construct(EventFactoryInterface $factory, $base)
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
            event_free($this->timers->getInfo());
        }
        
        // Need to completely destroy timer events before freeing base or an error is generated.
        $this->timers = null;
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
        
        $event = event_new();
        event_timer_set($event, $this->callback, $timer);
        event_base_set($event, $this->base);
        
        $this->timers[$timer] = $event;
        
        event_add($event, $timer->getInterval() * self::MICROSEC_PER_SEC);
        
        return $timer;
    }
    
    /**
     * @inheritdoc
     */
    public function cancel(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            event_free($this->timers[$timer]);
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function isPending(TimerInterface $timer)
    {
        return isset($this->timers[$timer]);
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
            event_free($this->timers->getInfo());
        }
        
        $this->timers = new UnreferencableObjectStorage();
    }
    
    /**
     * @return  callable
     */
    protected function createCallback()
    {
        return function ($resource, $what, TimerInterface $timer) {
            if ($timer->isPeriodic()) {
                event_add($this->timers[$timer], $timer->getInterval() * self::MICROSEC_PER_SEC);
            } else {
                event_free($this->timers[$timer]);
                unset($this->timers[$timer]);
            }
            
            $timer->call();
        };
    }
}