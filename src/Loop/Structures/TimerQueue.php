<?php
namespace Icicle\Loop\Structures;

use Countable;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Structures\UnreferencableObjectStorage;
use SplPriorityQueue;

class TimerQueue implements Countable
{
    /**
     * @var SplPriorityQueue
     */
    protected $queue;
    
    /**
     * @var UnreferencableObjectStorage
     */
    protected $timers;
    
    /**
     */
    public function __construct()
    {
        $this->queue = new SplPriorityQueue();
        $this->timers = new UnreferencableObjectStorage();
    }
    
    /**
     * Adds the timer to the queue.
     *
     * @param   TimerInterface $timer
     */
    public function add(TimerInterface $timer)
    {
        if (!$this->timers->contains($timer)) {
            $timeout = microtime(true) + $timer->getInterval();
            $this->queue->insert($timer, -$timeout);
            $this->timers[$timer] = $timeout;
        }
    }
    
    /**
     * Determines if the timer is in the queue.
     *
     * @param   TimerInterface $timer
     *
     * @return  bool
     */
    public function contains(TimerInterface $timer)
    {
        return $this->timers->contains($timer);
    }
    
    /**
     * Removes the timer from the queue.
     *
     * @param   TimerInterface $timer
     */
    public function remove(TimerInterface $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers->detach($timer);
        }
    }
    
    /**
     * @param   TimerInterface $timer
     */
    public function unreference(TimerInterface $timer)
    {
        $this->timers->unreference($timer);
    }
    
    /**
     * @param   TimerInterface $timer
     */
    public function reference(TimerInterface $timer)
    {
        $this->timers->reference($timer);
    }
    
    /**
     * Returns the number of referenced timers in the queue.
     *
     * @return  int
     */
    public function count()
    {
        return $this->timers->count();
    }
    
    /**
     * Determines if the queue is empty (includes unreferenced timers).
     *
     * @return  bool
     */
    public function isEmpty()
    {
        return $this->timers->isEmpty();
    }
    
    /**
     * Removes all timers in the queue. Safe to call during call to tick().
     */
    public function clear()
    {
        $this->queue = new SplPriorityQueue();
        $this->timers = new UnreferencableObjectStorage();
    }
    
    /**
     * Calculates the time remaining until the top timer is ready. Returns null if no timers are in the queue, otherwise a
     * non-negative value is returned.
     *
     * @return  int|float|null
     */
    public function getInterval()
    {
        while (!$this->queue->isEmpty()) {
            $timer = $this->queue->top();
            
            if ($this->timers->contains($timer)) {
                $timeout = $this->timers[$timer] - microtime(true);
                
                if (0 > $timeout) {
                    return 0;
                }
                
                return $timeout;
            } else {
                $this->queue->extract(); // Timer was removed from queue.
            }
        }
        
        return null;
    }
    
    /**
     * Executes any pending timers. Returns the number of timers executed.
     *
     * @return  int
     */
    public function tick()
    {
        $count = 0;
        
        while (!$this->queue->isEmpty()) {
            $timer = $this->queue->top();
            
            if ($this->timers->contains($timer)) {
                if ($this->timers[$timer] > microtime(true)) { // Timer at top of queue has not expired.
                    return $count;
                }
                
                // Remove and execute timer. Replace timer if persistent.
                $this->queue->extract();
                
                if ($timer->isPeriodic()) {
                    $this->timers[$timer] += $timer->getInterval();
                    $this->queue->insert($timer, -$this->timers[$timer]);
                } else {
                    $this->timers->detach($timer);
                }
                
                // Execute the timer.
                $timer->call();
                
                ++$count;
            } else {
                $this->queue->extract(); // Timer was removed from queue.
            }
        }
        
        return $count;
    }
}
