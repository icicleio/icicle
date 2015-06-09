<?php
namespace Icicle\Promise\Structures;

class ThenQueue
{
    /**
     * @var \SplQueue
     */
    private $queue;
    
    /**
     */
    public function __construct()
    {
        $this->queue = new \SplQueue();
    }
    
    /**
     * Calls each callback in the queue, passing the provided value to the function.
     *
     * @param mixed $value
     */
    public function __invoke($value)
    {
        /** @var callable $callback */
        foreach ($this->queue as $callback) {
            $callback($value);
        }
    }
    
    /**
     * Unrolls instances of self to avoid blowing up the call stack on resolution.
     *
     * @param callable $resolver
     */
    public function push(callable $resolver)
    {
        if (!$resolver instanceof self) {
            $this->queue->push($resolver);
            return;
        }

        foreach ($resolver->queue as $callback) {
            $this->queue->push($callback);
        }
    }
}
