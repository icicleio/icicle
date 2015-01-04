<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Exception\InvalidArgumentException;
use Icicle\Loop\LoopInterface;

class Poll implements PollInterface
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
     * @var callable|null
     */
    private $callback;
    
    /**
     * @param   LoopInterface $loop
     * @param   resource $resource
     * @param   callable $callback
     */
    public function __construct(LoopInterface $loop, $resource, callable $callback)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Must provide a socket or stream resource.');
        }
        
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
        $this->loop->listenPoll($this, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->loop->isPollPending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed()
    {
        return $this->loop->isPollFreed($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->loop->cancelPoll($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function free()
    {
        $this->loop->freePoll($this);
    }
    
    /**
     * @return  resource
     */
    public function getResource()
    {
        return $this->resource;
    }
}
