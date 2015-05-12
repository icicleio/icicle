<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Events\Manager\ImmediateManagerInterface;

class Immediate implements ImmediateInterface
{
    /**
     * @var \Icicle\Loop\Events\Manager\ImmediateManagerInterface
     */
    private $manager;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   \Icicle\Loop\Events\Manager\ImmediateManagerInterface $manager
     * @param   callable $callback Function called when the interval expires.
     * @param   array $args Optional array of arguments to pass the callback function.
     */
    public function __construct(ImmediateManagerInterface $manager, callable $callback, array $args = null)
    {
        $this->manager = $manager;
        
        if (empty($args)) {
            $this->callback = $callback;
        } else {
            $this->callback = function () use ($callback, $args) {
                call_user_func_array($callback, $args);
            };
        }
    }
    
    /**
     * @inheritdoc
     */
    public function call()
    {
        $callback = $this->callback;
        $callback();
    }
    
    /**
     * @inheritdoc
     */
    public function __invoke()
    {
        $this->call();
    }
    
    /**
     * @inheritdoc
     */
    public function isPending()
    {
        return $this->manager->isPending($this);
    }
    
    /**
     * @inheritdoc
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }
    
    /**
     * @inheritdoc
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
