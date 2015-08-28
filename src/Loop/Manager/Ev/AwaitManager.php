<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\Events\SocketEventInterface;

class AwaitManager extends SocketManager
{
    /**
     * {@inheritdoc}
     */
    protected function createEvent(\EvLoop $loop, SocketEventInterface $socket, callable $callback)
    {
        $event = $loop->io($socket->getResource(), \Ev::WRITE, $callback, $socket);
        $event->stop();

        return $event;
    }
}
