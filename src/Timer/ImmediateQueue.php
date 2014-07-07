<?php
namespace Icicle\Timer;

use Countable;
use SplObjectStorage;
use SplQueue;

class ImmediateQueue implements Countable
{
    /**
     * @var     SplQueue
     */
    private $queue;
    
    /**
     * @var     SplObjectStorage
     */
    private $immediates;
    
    /**
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
        $this->immediates = new SplObjectStorage();
    }
    
    /**
     * Adds the timer to the queue.
     * @param   ImmediateInterface $immediate
     */
    public function add(ImmediateInterface $immediate)
    {
        if (!$this->immediates->contains($immediate)) {
            $this->queue->push($immediate);
            $this->immediates->attach($immediate);
        }
    }
    
    /**
     * Determines if the timer is in the queue.
     * @param   ImmediateInterface $immediate
     * @return  bool
     */
    public function contains(ImmediateInterface $immediate)
    {
        return $this->immediates->contains($immediate);
    }
    
    /**
     * Removes the immediate from the queue.
     * @param   ImmediateInterface $immediate
     */
    public function remove(ImmediateInterface $immediate)
    {
        if ($this->immediates->contains($immediate)) {
            foreach ($this->queue as $key => $value) {
                if ($value === $immediate) {
                    unset($this->queue[$key]);
                    break;
                }
            }
            
            $this->immediates->detach($immediate);
        }
    }
    
    /**
     * Returns the number of immediates in the queue.
     * @return  int
     */
    public function count()
    {
        return $this->immediates->count();
    }
    
    /**
     * Alias of count().
     * @return  int
     */
    public function getLength()
    {
        return $this->count();
    }
    
    /**
     * Determines if the queue is empty.
     * @return  bool
     */
    public function isEmpty()
    {
        return 0 === $this->immediates->count();
    }
    
    /**
     * Removes all immediates in the queue. Safe to call during call to tick().
     */
    public function clear()
    {
        $this->queue = new SplQueue();
        $this->immediates = new SplObjectStorage();
    }
    
    /**
     * Executes the next immediate.
     * @return  int
     */
    public function tick()
    {
        if (!$this->queue->isEmpty()) {
            $immediate = $this->queue->shift();
            $this->immediates->detach($immediate);
            
            $immediate->call(); // Execute the immediate.
        }
    }
}
