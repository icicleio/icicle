<?php
namespace Icicle\Loop\Events;

interface SocketEventInterface
{
    /**
     * Returns the PHP resource.
     *
     * @return resource
     */
    public function getResource();
    
    /**
     * @param bool $expired
     */
    public function call($expired);
    
    /**
     * @param bool $expired
     */
    public function __invoke($expired);

    /**
     * Listens for data or the ability to write.
     *
     * @param int|float $timeout Number of seconds until the callback is invoked with $expired set to true if
     *     no data is received or the socket does not become writable. Use null for no timeout.
     */
    public function listen($timeout = 0);

    /**
     * Stops listening for data or the ability to write on the socket.
     */
    public function cancel();

    /**
     * Determines if the socket event is currently listening for data or the ability to write.
     *
     * @return bool
     */
    public function isPending();
    
    /**
     * Frees the resources used to listen for events on the socket.
     */
    public function free();
    
    /**
     * @return bool
     */
    public function isFreed();
}
