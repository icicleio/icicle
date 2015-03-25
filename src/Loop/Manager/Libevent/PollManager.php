<?php
namespace Icicle\Loop\Manager\Libevent;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Manager\PollManagerInterface;

class PollManager extends SocketManager implements PollManagerInterface
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
        return $this->factory->createPoll($this, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    protected function createEvent($base, SocketEventInterface $socket, callable $callback)
    {
        $event = event_new();
        event_set($event, $socket->getResource(), EV_READ, $callback, $socket);
        event_base_set($event, $base);
        
        return $event;
    }
}
