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
     * @param   LoopInterface $loop
     * @param   resource $resource
     * @param   callable $callback
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
    public function call($resource, $expired = false)
    {
        $callback = $this->callback;
        $callback($resource, $expired);
    }
    
    /**
     * {@inheritdoc}
     */
    public function __invoke($resource, $expired = false)
    {
        $this->call($resource, $expired);
    }
    
    /**
     * {@inheritdoc}
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCallback()
    {
        return $this->callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function listen($timeout = null)
    {
        $this->loop->listenAwait($this, $timeout);
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
}
