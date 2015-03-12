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
}
