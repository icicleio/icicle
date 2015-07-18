<?php
namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\SocketEventInterface;

class AwaitManager extends SocketManager
{
    /**
     * {@inheritdoc}
     */
    protected function createEvent(EventBase $base, SocketEventInterface $socket, callable $callback): Event
    {
        return new Event($base, $socket->getResource(), Event::WRITE, $callback, $socket);
    }
}
