<?php
namespace Icicle\Loop\Events;

interface SocketEventInterface extends EventInterface
{
    /**
     * Returns the PHP resource.
     *
     * @return  resource
     */
    public function getResource();
    
    /**
     * @param   bool $expired
     */
    public function call($expired);
    
    /**
     * @param   bool $expired
     */
    public function __invoke($expired);
    
    /**
     * Sets the function to be called when an event occurs on the socket.
     *
     * @param   callable $callback
     */
    public function setCallback(callable $callback);
    
    /**
     * Listens for events on the socket.
     *
     * @param   int|float|null $timeout Number of seconds until the callback is invoked with the expired param set to true.
     *          Use null for no timeout.
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
}
