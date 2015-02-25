<?php
namespace Icicle\Promise\Structures;

use SplQueue;

class ThenQueue implements \Countable
{
    /**
     * @var     SplQueue
     */
    private $queue;
    
    /**
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
    }
    
    /**
     * @param   callable $callback
     */
    public function insert(callable $callback)
    {
        $this->queue->push($callback);
    }
    
    /**
     * Calls each callback in the queue, passing the provided value to the function.
     *
     * @param   mixed $value
     */
    public function __invoke($value)
    {
        foreach ($this->queue as $callback) {
            $callback($value);
        }
    }
    
    /**
     * Returns the number of callbacks in the queue.
     *
     * @return  int
     */
    public function count()
    {
        return $this->queue->count();
    }
    
    /**
     * Determines if any callbacks have been defined in the queue.
     *
     * @return  bool
     */
    public function isEmpty()
    {
        return $this->queue->isEmpty();
    }
    
    /**
     * Removes all callbacks from the queue.
     */
    public function clear()
    {
        $this->queue = new SplQueue();
    }
}
