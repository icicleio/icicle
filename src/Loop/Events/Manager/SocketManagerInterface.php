<?php
namespace Icicle\Loop\Events\Manager;

use Icicle\Loop\Events\SocketEventInterface;

interface SocketManagerInterface
{
    /**
     * Returns a SocketEventInterface object for the given stream socket resource.
     *
     * @param resource $resource
     * @param callable $callback
     *
     * @return \Icicle\Loop\Events\SocketEventInterface
     */
    public function create($resource, callable $callback);
    
    /**
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     * @param float|int $timeout
     */
    public function listen(SocketEventInterface $event, $timeout = 0);
    
    /**
     * Cancels the given socket operation.
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     */
    public function cancel(SocketEventInterface $event);
    
    /**
     * Determines if the socket event is enabled (listening for data or space to write).
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     *
     * @return bool
     */
    public function isPending(SocketEventInterface $event);
    
    /**
     * Frees the given socket event.
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     */
    public function free(SocketEventInterface $event);
    
    /**
     * Determines if the socket event has been freed.
     *
     * @param \Icicle\Loop\Events\SocketEventInterface $event
     *
     * @return bool
     */
    public function isFreed(SocketEventInterface $event);

    /**
     * Determines if any socket events are pending in the manager.
     *
     * @return bool
     */
    public function isEmpty();

    /**
     * Clears all socket events from the manager.
     */
    public function clear();
}
