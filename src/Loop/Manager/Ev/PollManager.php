<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\Events\SocketEventInterface;

class PollManager extends SocketManager
{
    /**
     * {@inheritdoc}
     */
    protected function createEvent(\EvLoop $loop, SocketEventInterface $socket, callable $callback): \EvIO
    {
        $event = $loop->io($socket->getResource(), \Ev::READ, $callback, $socket);
        $event->stop();

        return $event;
    }
}
