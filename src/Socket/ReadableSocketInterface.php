<?php
namespace Icicle\Socket;

interface ReadableSocketInterface extends SocketInterface
{
    const NO_TIMEOUT = 0;
    
    const MIN_TIMEOUT = 0.001;
    
    const DEFAULT_TIMEOUT = 30;
    
    /**
     * Returns the number of seconds until the connection times out if no read events have occurred.
     *
     * @return  float Number of seconds. Use 0 for no timeout.
     *
     * @api
     */
    public function getTimeout();
    
    /**
     * Sets the number of seconds until the connection times out.
     *
     * @param   float Number of seconds. Use 0 for no timeout.
     */
    public function setTimeout($timeout);
    
    /**
     * Called when the connection has data waiting to be read or a connection to be accepted.
     */
    public function onRead();
    
    /**
     * Called by loop when the connection has timed out.
     */
    public function onTimeout();
}
