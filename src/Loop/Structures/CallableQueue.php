<?php
namespace Icicle\Loop\Structures;

/**
 * Creates a queue of callable functions that can be invoked in the order queued. Once a function is invoked from the
 * queue, the function is removed from the queue.
 */
class CallableQueue implements \Countable
{
    /**
     * @var callable[]
     */
    private $queue = [];
    
    /**
     * @var int
     */
    private $maxDepth = 0;
    
    /**
     * @param int $depth
     */
    public function __construct($depth = 0)
    {
        if (0 !== $depth) {
            $this->maxDepth($depth);
        }
    }
    
    /**
     * @param callable $callback
     * @param mixed[]|null $args
     */
    public function insert(callable $callback, array $args = null)
    {
        $this->queue[] = [$callback, $args];
    }
    
    /**
     * Number of callbacks in the queue.
     *
     * @return int
     */
    public function count()
    {
        return count($this->queue);
    }
    
    /**
     * Determines if the queue is empty.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return empty($this->queue);
    }
    
    /**
     * Removes all callbacks from the queue.
     */
    public function clear()
    {
        $this->queue = [];
    }
    
    /**
     * Sets the maximum number of functions that can be called when the queue is called.
     *
     * @param int|null $depth
     *
     * @return int Current max depth if $depth = null or previous max depth otherwise.
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
     * @return int Number of functions called.
     */
    public function call()
    {
        $count = 0;

        try {
            while (isset($this->queue[$count]) && (0 === $this->maxDepth || $count < $this->maxDepth)) {
                list($callback, $args) = $this->queue[$count++];

                if (empty($args)) {
                    $callback();
                } else {
                    call_user_func_array($callback, $args);
                }
            }
        } finally {
            $this->queue = array_slice($this->queue, $count);
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
