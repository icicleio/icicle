<?php
namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\SocketEventInterface;

interface SocketManagerInterface extends ManagerInterface
{
    /**
     * Returns a SocketEventInterface object for the given stream socket resource.
     *
     * @param   resource $resource
     * @param   callable $callback
     *
     * @return  SocketEventInterface
     */
    public function create($resource, callable $callback);
    
    /**
     * @param   SocketEventInterface $event
     * @param   float|null $timeout
     */
    public function listen(SocketEventInterface $event, $timeout = null);
    
    /**
     * Cancels the given poll operation.
     *
     * @param   SocketEventInterface $event
     */
    public function cancel(SocketEventInterface $event);
    
    /**
     * Determines if the poll is pending (listening for data).
     *
     * @param   SocketEventInterface $event
     *
     * @return  bool
     */
    public function isPending(SocketEventInterface $event);
    
    /**
     * Frees the given poll.
     *
     * @param   SocketEventInterface $event
     */
    public function free(SocketEventInterface $event);
    
    /**
     * Determines if the poll has been freed.
     *
     * @param   SocketEventInterface $event
     *
     * @return  bool
     */
    public function isFreed(SocketEventInterface $event);
}
