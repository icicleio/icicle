<?php
namespace Icicle\Promise\Structures;

class ThenQueue extends \SplQueue
{
    /**
     * Calls each callback in the queue, passing the provided value to the function.
     *
     * @param   mixed $value
     */
    public function __invoke($value)
    {
        foreach ($this as $callback) {
            $callback($value);
        }
    }
    
    /**
     * Unrolls instances of self to avoid blowing up the call stack on resolution.
     *
     * @param   callable $callback
     */
    public function push(callable $resolver)
    {
        if ($resolver instanceof self) {
            foreach ($resolver as $callback) {
                parent::push($callback);
            }
        } else {
            parent::push($resolver);
        }
    }
}
