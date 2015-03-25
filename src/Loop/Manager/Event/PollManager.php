<?php
namespace Icicle\Loop\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\SocketEventInterface;

class PollManager extends SocketManager
{
    /**
     * @inheritdoc
     */
    protected function createEvent(EventBase $base, SocketEventInterface $socket, callable $callback)
    {
        return new Event($base, $socket->getResource(), Event::READ, $callback, $socket);
    }
}
