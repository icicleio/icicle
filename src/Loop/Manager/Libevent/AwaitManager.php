<?php
namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Manager\AwaitManagerInterface;

class AwaitManager extends SocketManager implements AwaitManagerInterface
{
    /**
     * @var EventFactoryInterface
     */
    private $factory;
    
    /**
     * @param   EventFactoryInterface $factory
     * @param   resource $base
     */
    public function __construct(EventFactoryInterface $factory, $base)
    {
        parent::__construct($base);
        
        $this->factory = $factory;
    }
    
    /**
     * @inheritdoc
     */
    protected function createSocketEvent($resource, callable $callback)
    {
        return $this->factory->createAwait($this, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    protected function createEvent($base, SocketEventInterface $socket, callable $callback)
    {
        $event = event_new();
        event_set($event, $socket->getResource(), EV_WRITE, $callback, $socket);
        event_base_set($event, $base);
        
        return $event;
    }
}
