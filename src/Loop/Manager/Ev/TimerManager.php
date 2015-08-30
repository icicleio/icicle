<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\Events\{EventFactoryInterface, TimerInterface};
use Icicle\Loop\EvLoop;
use Icicle\Loop\Manager\TimerManagerInterface;
use Icicle\Loop\Structures\ObjectStorage;

class TimerManager implements TimerManagerInterface
{
    /**
     * @var \EvLoop
     */
    private $loop;
    
    /**
     * @var EventFactoryInterface
     */
    private $factory;
    
    /**
     * ObjectStorage mapping Timer objects to EvTimer objects.
     *
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $timers;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   \Icicle\Loop\EvLoop $loop
     * @param   \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(EvLoop $loop, EventFactoryInterface $factory)
    {
        $this->factory = $factory;
        $this->loop = $loop->getEvLoop();
        
        $this->timers = new ObjectStorage();
        
        $this->callback = function (\EvTimer $event) {
            /** @var \Icicle\Loop\Events\TimerInterface $timer */
            $timer = $event->data;

            if (!$timer->isPeriodic()) {
                $event->stop();
                unset($this->timers[$timer]);
            } else {
                $event->again();
            }

            $timer->call();
        };
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->stop();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return !$this->timers->count();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create(float $interval, bool $periodic, callable $callback, array $args = []): TimerInterface
    {
        $timer = $this->factory->timer($this, $interval, $periodic, $callback, $args);
        
        $event = $this->loop->timer($interval, $periodic ? $interval : 0, $this->callback, $timer);
        
        $this->timers[$timer] = $event;

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function start(TimerInterface $timer)
    {
        if (!isset($this->timers[$timer])) {
            $interval = $timer->getInterval();

            $event = $this->loop->timer($interval, $timer->isPeriodic() ? $interval : 0, $this->callback, $timer);

            $this->timers[$timer] = $event;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->stop();
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(TimerInterface $timer): bool
    {
        return isset($this->timers[$timer]) && $this->timers[$timer]->is_active;
    }
    
    /**
     * {@inheritdoc}
     */
    public function unreference(TimerInterface $timer)
    {
        $this->timers->unreference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function reference(TimerInterface $timer)
    {
        $this->timers->reference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->stop();
        }
        
        $this->timers = new ObjectStorage();
    }
}