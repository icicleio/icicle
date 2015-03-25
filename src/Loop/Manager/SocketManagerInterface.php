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
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    public function create($resource, callable $callback);
    
    /**
     * @param   \Icicle\Loop\Events\SocketEventInterface $event
     * @param   float|null $timeout
     */
    public function listen(SocketEventInterface $event, $timeout = null);
    
    /**
     * Cancels the given poll operation.
     *
     * @param   \Icicle\Loop\Events\SocketEventInterface $event
     */
    public function cancel(SocketEventInterface $event);
    
    /**
     * Determines if the poll is pending (listening for data).
     *
     * @param   \Icicle\Loop\Events\SocketEventInterface $event
     *
     * @return  bool
     */
    public function isPending(SocketEventInterface $event);
    
    /**
     * Frees the given poll.
     *
     * @param   \Icicle\Loop\Events\SocketEventInterface $event
     */
    public function free(SocketEventInterface $event);
    
    /**
     * Determines if the poll has been freed.
     *
     * @param   \Icicle\Loop\Events\SocketEventInterface $event
     *
     * @return  bool
     */
    public function isFreed(SocketEventInterface $event);
}
