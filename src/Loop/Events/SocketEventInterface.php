<?php
namespace Icicle\Loop\Events;

interface SocketEventInterface extends EventInterface
{
    /**
     * @param   resource $resource
     * @param   bool $expired
     */
    public function call($resource, $expired = false);    
    /**
     * @param   resource $resource
     * @param   bool $expired
     */
    public function __invoke($resource, $expired = false);
    
    /**
     * Sets the function to be called when an event occurs on the socket.
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
    
    /**
     * Returns the PHP resource.
     *
     * @return  resource
     */
    public function getResource();
}
