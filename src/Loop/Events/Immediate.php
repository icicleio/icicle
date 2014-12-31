<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\LoopInterface;

class Immediate implements ImmediateInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   callable $callback Function called when the interval expires.
     * @param   array $args Optional array of arguments to pass the callback function.
     */
    public function __construct(LoopInterface $loop, callable $callback, array $args = null)
    {
        $this->loop = $loop;
        
        if (empty($args)) {
            $this->callback = $callback;
        } else {
            $this->callback = function () use ($callback, $args) {
                call_user_func_array($callback, $args);
            };
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->loop->isImmediatePending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->loop->cancelImmediate($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
