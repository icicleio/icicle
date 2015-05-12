<?php
namespace Icicle\Loop\Events\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\TimerManagerInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Structures\UnreferencableObjectStorage;
use SplPriorityQueue;

class TimerManager implements TimerManagerInterface
{
    /**
     * @var EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var SplPriorityQueue
     */
    private $queue;
    
    /**
     * @var UnreferencableObjectStorage
     */
    private $timers;
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(EventFactoryInterface $factory)
    {
        $this->factory = $factory;
        
        $this->queue = new SplPriorityQueue();
        $this->timers = new UnreferencableObjectStorage();
    }
    
    /**
     * @inheritdoc
     */
    public function create(callable $callback, $interval, $periodic = false, array $args = null)
    {
        $timer = $this->factory->timer($this, $callback, $interval, $periodic, $args);
        
        $timeout = microtime(true) + $timer->getInterval();
        $this->queue->insert($timer, -$timeout);
        $this->timers[$timer] = $timeout;
        
        return $timer;
    }
    
    /**
     * @inheritdoc
     */
    public function isPending(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }
    
    /**
     * @inheritdoc
     */
    public function cancel(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers->detach($timer);
        }
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
    public function isEmpty()
    {
        return !$this->timers->count();
    }
    
    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->queue = new SplPriorityQueue();
        $this->timers = new UnreferencableObjectStorage();
    }
    
    /**
     * Calculates the time remaining until the top timer is ready. Returns null if no timers are in the queue, otherwise
     * a non-negative value is returned.
     *
     * @return  int|float|null
     *
     * @internal
     */
    public function getInterval()
    {
        while (!$this->queue->isEmpty()) {
            $timer = $this->queue->top();

            if (!$this->timers->contains($timer)) { // Timer was removed from queue.
                $this->queue->extract();
                continue;
            }

            $timeout = $this->timers[$timer] - microtime(true);

            if (0 > $timeout) {
                return 0;
            }

            return $timeout;
        }
        
        return null;
    }
    
    /**
     * Executes any pending timers. Returns the number of timers executed.
     *
     * @return  int
     *
     * @internal
     */
    public function tick()
    {
        $count = 0;
        
        while (!$this->queue->isEmpty()) {
            $timer = $this->queue->top();
            
            if (!$this->timers->contains($timer)) { // Timer was removed from queue.
                $this->queue->extract();
                continue;
            }

            if ($this->timers[$timer] > microtime(true)) { // Timer at top of queue has not expired.
                return $count;
            }

            // Remove and execute timer. Replace timer if persistent.
            $this->queue->extract();

            if ($timer->isPeriodic()) {
                $timeout = microtime(true) + $timer->getInterval();
                $this->queue->insert($timer, -$timeout);
                $this->timers[$timer] = $timeout;
            } else {
                $this->timers->detach($timer);
            }

            // Execute the timer.
            $timer->call();

            ++$count;
        }
        
        return $count;
    }
}
