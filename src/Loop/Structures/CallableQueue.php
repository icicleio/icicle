<?php
namespace Icicle\Loop\Structures;

/**
 * Creates a queue of callable functions that can be invoked in the order queued. Once a function is invoked from the
 * queue, the function is removed from the queue.
 */
class CallableQueue implements \Countable
{
    /**
     * @var     \SplQueue
     */
    private $queue;
    
    /**
     * @var     int
     */
    private $maxDepth = 0;
    
    /**
     * @param null $depth
     */
    public function __construct($depth = null)
    {
        $this->queue = new \SplQueue();
        
        if (null !== $depth) {
            $this->maxDepth($depth);
        }
    }
    
    /**
     * @param   callable $callback
     * @param   mixed[]|null $args
     */
    public function insert(callable $callback, array $args = null)
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
        $this->queue = new \SplQueue();
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
            $this->maxDepth = 0 > $depth ? 0 : $depth;
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
        
        while (!$this->queue->isEmpty() && (++$count <= $this->maxDepth || 0 === $this->maxDepth)) {
            /** @var callable $callback */
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
