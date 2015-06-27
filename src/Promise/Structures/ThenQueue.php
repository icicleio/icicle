<?php
namespace Icicle\Promise\Structures;

class ThenQueue
{
    /**
     * @var callable[]
     */
    private $queue = [];
    
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
     * @param callable $resolver
     */
    public function push(callable $resolver)
    {
        if ($resolver instanceof self) {
            $this->queue = array_merge($this->queue, $resolver->queue);
            return;
        }

        $this->queue[] = $resolver;
    }
}
