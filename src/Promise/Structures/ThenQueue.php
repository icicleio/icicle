<?php
namespace Icicle\Promise\Structures;

class ThenQueue
{
    /**
     * @var callable[]
     */
    private $queue = [];

    /**
     * @param callable|null $callback Initial callback to add to queue.
     */
    public function __construct(callable $callback = null)
    {
        if (null !== $callback) {
            $this->push($callback);
        }
    }

    /**
     * Calls each callback in the queue, passing the provided value to the function.
     *
     * @param mixed $value
     */
    public function __invoke($value)
    {
        foreach ($this->queue as $callback) {
            $callback($value);
        }
    }
    
    /**
     * Unrolls instances of self to avoid blowing up the call stack on resolution.
     *
     * @param callable $callback
     */
    public function push(callable $callback)
    {
        if ($callback instanceof self) {
            $this->queue = array_merge($this->queue, $callback->queue);
            return;
        }

        $this->queue[] = $callback;
    }
}
