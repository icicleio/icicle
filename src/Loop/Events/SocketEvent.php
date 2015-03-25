<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Exception\InvalidArgumentException;
use Icicle\Loop\Manager\SocketManagerInterface;

/**
 * Represents read and write (poll and await) socket events.
 */
class SocketEvent implements SocketEventInterface
{
    /**
     * @var SocketManagerInterface
     */
    private $manager;
    
    /**
     * @var resource
     */
    private $resource;
    
    /**
     * @var callable|null
     */
    private $callback;
    
    /**
     * @param   SocketManagerInterface $manager
     * @param   resource $resource
     * @param   callable $callback
     */
    public function __construct(SocketManagerInterface $manager, $resource, callable $callback)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Must provide a socket or stream resource.');
        }
        
        $this->manager = $manager;
        $this->resource = $resource;
        $this->callback = $callback;
    }
    
    /**
     * @inheritdoc
     */
    public function call($resource, $expired = false)
    {
        $callback = $this->callback;
        $callback($resource, $expired);
    }
    
    /**
     * @inheritdoc
     */
    public function __invoke($resource, $expired = false)
    {
        $this->call($resource, $expired);
    }
    
    /**
     * @inheritdoc
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * @inheritdoc
     */
    public function getCallback()
    {
        return $this->callback;
    }
    
    /**
     * @inheritdoc
     */
    public function listen($timeout = null)
    {
        $this->manager->listen($this, $timeout);
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
    public function isFreed()
    {
        return $this->manager->isFreed($this);
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
    public function free()
    {
        $this->manager->free($this);
    }
    
    /**
     * @return  resource
     */
    public function getResource()
    {
        return $this->resource;
    }
}
