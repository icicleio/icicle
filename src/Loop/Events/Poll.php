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
     * @param   callable $callback Function called when the interval expires.
     * @param   array $args Optional array of arguments to pass the callback function.
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
    
    public function set(callable $callback)
    {
        $this->callback = $callback;
    }
    
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
    
    /**
     * @return  callable
     */
    public function getCallback()
    {
        return $this->callback;
    }
}
