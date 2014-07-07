<?php
namespace Icicle\Structures;

use SplQueue;

class CallableQueue implements \Countable
{
    const DEFAULT_MAX_DEPTH = 1000;
    
    /**
     * @var     SplQueue
     */
    private $queue;
    
    /**
     * @var     int
     */
    private $maxDepth = self::DEFAULT_MAX_DEPTH;
    
    /**
     */
    public function __construct()
    {
        $this->queue = new SplQueue();
    }
    
    /**
     * @param   callable $callback
     * @param   array $args
     */
    public function insert(callable $callback, array $args = [])
    {
        if (!empty($args)) {
            $callback = function () use ($callback, $args) {
                call_user_func_array($callback, $args);
            };
        }
        
        $this->queue->push($callback);
    }
    
    /**
     * Number of callbacks in the queue.
     *
     * @return  int
     */
    public function count()
    {
        return $this->queue->count();
    }
    
    /**
     * Alias of count().
     *
     * @return  int
     */
    public function getLength()
    {
        return $this->count();
    }
    
    /**
     * Determines if the queue is empty.
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
    
    /**
     * Sets the maximum number of functions that can be called when the queue is called.
     *
     * @param   int $depth
     */
    public function maxDepth($depth)
    {
        $depth = (int) $depth;
        $this->maxDepth = 0 < $depth ? $depth : 1;
    }
    
    /**
     * Executes each callback that was in the queue when this method is called up to the maximum depth.
     */
    public function call()
    {
        for ($count = 0; !$this->queue->isEmpty() && $count < $this->maxDepth; ++$count) {
            $callback = $this->queue->shift();
            $callback();
        }
    }
    
    /**
     * Alias of call().
     */
    public function __invoke()
    {
        $this->call();
    }
}
