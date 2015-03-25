<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\PollManagerInterface;

/**
 * Socket event used to poll a socket for available data.
 */
class Poll extends SocketEvent implements PollInterface
{
    /**
     * Enforces type of manager passed to parent constructor.
     *
     * @param   PollManagerInterface $manager
     * @param   resource $resource Stream socket resource.
     * @param   callable $callback
     */
    public function __construct(PollManagerInterface $manager, $resource, callable $callback)
    {
        parent::__construct($manager, $resource, $callback);
    }
}
