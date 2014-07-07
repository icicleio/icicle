<?php
namespace Icicle\Timer;

use Icicle\Core\Base;
use Icicle\Loop\Loop;

class Immediate implements ImmediateInterface
{
    /**
     * Callback function.
     * @var     callable
     */
    private $callback;
    
    /**
     * Optional array of arguments to be passed to the callback function.
     * @var     array
     */
    private $args;
    
    /**
     * @param   callable $callback Function called when the interval expires.
     * @param   array $args Optional array of arguments to pass the callback function.
     */
    public function __construct(callable $callback, array $args = [])
    {
        $this->callback = $callback;
        $this->args = $args;
        
        Loop::getInstance()->addImmediate($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function schedule()
    {
        Loop::getInstance()->addImmediate($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return Loop::getInstance()->isImmediatePending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        Loop::getInstance()->cancelImmediate($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function call()
    {
        call_user_func_array($this->callback, $this->args);
    }
    
    /**
     * Creates an Immediate using the given callback and arguments.
     *
     * @param   callable $callback
     * @param   mixed ...$args Optional arguments to pass to the callback function.
     *
     * @api
     */
    public static function enqueue(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        return new static($callback, $args);
    }
}
