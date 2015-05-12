<?php
namespace Icicle\Loop\Events\Manager;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\ImmediateInterface;
use SplObjectStorage;
use SplQueue;

class ImmediateManager implements ImmediateManagerInterface
{
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;
    
    /**
     * @var \SplQueue
     */
    private $queue;
    
    /**
     * @var \SplObjectStorage
     */
    private $immediates;
    
    /**
     * @param   EventFactoryInterface $factory
     */
    public function __construct(EventFactoryInterface $factory)
    {
        $this->factory = $factory;
        $this->queue = new SplQueue();
        $this->immediates = new SplObjectStorage();
    }
    
    /**
     * @inheritdoc
     */
    public function create(callable $callback, array $args = null)
    {
        $immediate = $this->factory->immediate($this, $callback, $args);
        
        $this->queue->push($immediate);
        $this->immediates->attach($immediate);
        
        return $immediate;
    }
    
    /**
     * @inheritdoc
     */
    public function isPending(ImmediateInterface $immediate)
    {
        return $this->immediates->contains($immediate);
    }
    
    /**
     * @inheritdoc
     */
    public function cancel(ImmediateInterface $immediate)
    {
        if ($this->immediates->contains($immediate)) {
            $this->immediates->detach($immediate);
        }
    }

    /**
     * @inheritdoc
     */
    public function isEmpty()
    {
        return 0 === $this->immediates->count();
    }
    
    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->queue = new SplQueue();
        $this->immediates = new SplObjectStorage();
    }
    
    /**
     * @inheritdoc
     */
    public function tick()
    {
        while (!$this->queue->isEmpty()) {
            $immediate = $this->queue->shift();
            
            if ($this->immediates->contains($immediate)) {
                $this->immediates->detach($immediate);
                
                // Execute the immediate.
                $immediate->call();
                
                return true;
            }
        }
        
        return false;
    }
}
