<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\LoopInterface;

class Await implements AwaitInterface
{
    /**
     * @var LoopInterface
     */
    private $loop;
    
    /**
     * @var resource
     */
    private $resource;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   callable $callback Function called when the interval expires.
     * @param   array $args Optional array of arguments to pass the callback function.
     */
    public function __construct(LoopInterface $loop, $resource, callable $callback)
    {
        $this->loop = $loop;
        $this->resource = $resource;
        $this->callback = $callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function set(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen()
    {
        $this->loop->listenAwait($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->loop->isAwaitPending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed()
    {
        return $this->loop->isAwaitFreed($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->loop->cancelAwait($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function free()
    {
        $this->loop->freeAwait($this);
    }
    
    /**
     * @return  resource
     */
    public function getResource()
    {
        return $this->resource;
    }
    
    /**
     * @return  callable
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
