<?php
namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
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
        return $this->factory->createPoll($this, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    protected function createEvent(EventBase $base, SocketEventInterface $socket, callable $callback)
    {
        return new Event($base, $socket->getResource(), Event::READ, $callback, $socket);
    }
}
