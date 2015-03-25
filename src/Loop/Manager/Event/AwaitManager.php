<?php
namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
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
     * @param   EventBase $base
     */
    public function __construct(EventFactoryInterface $factory, EventBase $base)
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
    protected function createEvent(EventBase $base, SocketEventInterface $socket, callable $callback)
    {
        return new Event($base, $socket->getResource(), Event::WRITE, $callback, $socket);
    }
}
