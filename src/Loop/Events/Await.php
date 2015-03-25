<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\AwaitManagerInterface;

/**
 * Socket event used to wait for available space to write on a stream socket.
 */
class Await extends SocketEvent implements AwaitInterface
{
    /**
     * Enforces type of manager passed to parent constructor.
     *
     * @param   PollManagerInterface $manager
     * @param   resource $resource Stream socket resource.
     * @param   callable $callback
     */
    public function __construct(AwaitManagerInterface $manager, $resource, callable $callback)
    {
        parent::__construct($manager, $resource, $callback);
    }
}
