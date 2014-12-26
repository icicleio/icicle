<?php
namespace Icicle\Loop\Events;

interface SocketEventInterface extends EventInterface
{
    /**
     * Sets the function to be called when an event occurs on the socket.
     */
    public function set(callable $callback);
    
    /**
     * Listens for events on the socket.
     */
    public function listen($timeout = null);
    
    /**
     * Frees the resources used to listen for events on the socket.
     */
    public function free();
    
    /**
     * @return  bool
     */
    public function isFreed();
    
    /**
     * Returns the PHP resource.
     *
     * @return  resource
     */
    public function getResource();
}
