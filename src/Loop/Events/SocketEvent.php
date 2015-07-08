<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Exception\NonResourceError;
use Icicle\Loop\Manager\SocketManagerInterface;

/**
 * Represents read and write (poll and await) socket events.
 */
class SocketEvent implements SocketEventInterface
{
    /**
     * @var \Icicle\Loop\Manager\SocketManagerInterface
     */
    private $manager;
    
    /**
     * @var resource
     */
    private $resource;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param \Icicle\Loop\Manager\SocketManagerInterface $manager
     * @param resource $resource
     * @param callable $callback
     *
     * @throws \Icicle\Loop\Exception\NonResourceError If a non-resource is given for $resource.
     */
    public function __construct(SocketManagerInterface $manager, $resource, callable $callback)
    {
        if (!is_resource($resource)) {
            throw new NonResourceError('Must provide a socket or stream resource.');
        }
        
        $this->manager = $manager;
        $this->resource = $resource;
        $this->callback = $callback;
    }
    
    /**
     * {@inheritdoc}
     */
    public function call(bool $expired)
    {
        ($this->callback)($this->resource, $expired);
    }
    
    /**
     * {@inheritdoc}
     */
    public function __invoke(bool $expired)
    {
        $this->call($expired);
    }

    /**
     * {@inheritdoc}
     */
    public function listen(float $timeout = 0)
    {
        $this->manager->listen($this, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(): bool
    {
        return $this->manager->isPending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFreed(): bool
    {
        return $this->manager->isFreed($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function free()
    {
        $this->manager->free($this);
    }
    
    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->resource;
    }
}
