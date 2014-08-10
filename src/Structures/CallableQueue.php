<?php
namespace Icicle\Structures;

use Countable;
use SplQueue;

class CallableQueue implements Countable
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
     * @param   int|null $depth
     *
     * @return  int Current max depth if $depth = null or previous max depth otherwise.
     */
    public function maxDepth($depth = null)
    {
        $previous = $this->maxDepth;
        
        if (null !== $depth) {
            $depth = (int) $depth;
            $this->maxDepth = 1 > $depth ? 1 : $depth;
        }
        
        return $previous;
    }
    
    /**
     * Executes each callback that was in the queue when this method is called up to the maximum depth.
     * 
     * @return  int Number of functions called.
     */
    public function call()
    {
        $count = 0;
        
        while (!$this->queue->isEmpty() && ++$count <= $this->maxDepth) {
            $callback = $this->queue->shift();
            $callback();
        }
        
        return $count;
    }
    
    /**
     * Alias of call().
     */
    public function __invoke()
    {
        return $this->call();
    }
}
