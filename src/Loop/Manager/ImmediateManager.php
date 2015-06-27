<?php
namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\ImmediateInterface;

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
     * @param EventFactoryInterface $factory
     */
    public function __construct(EventFactoryInterface $factory)
    {
        $this->factory = $factory;
        $this->queue = new \SplQueue();
        $this->immediates = new \SplObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create(callable $callback, array $args = null)
    {
        $immediate = $this->factory->immediate($this, $callback, $args);
        
        $this->execute($immediate);
        
        return $immediate;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(ImmediateInterface $immediate)
    {
        return $this->immediates->contains($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ImmediateInterface $immediate)
    {
        if (!$this->immediates->contains($immediate)) {
            $this->queue->push($immediate);
            $this->immediates->attach($immediate);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(ImmediateInterface $immediate)
    {
        if ($this->immediates->contains($immediate)) {
            $this->immediates->detach($immediate);

            foreach ($this->queue as $key => $event) {
                if ($event === $immediate) {
                    unset($this->queue[$key]);
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return 0 === $this->immediates->count();
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = new \SplQueue();
        $this->immediates = new \SplObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick()
    {
        if (!$this->queue->isEmpty()) {
            $immediate = $this->queue->shift();

            $this->immediates->detach($immediate);

            // Execute the immediate.
            $immediate->call();

            return true;
        }

        return false;
    }
}
