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
     * @return  int Number of seconds until a timeout event is triggered if no data is read. 0 = no timeout.
     *
     * @api
     */
    public function getTimeout();
    
    /**
     * Called when the connection has data waiting to be read or a connection to be accepted.
     */
    public function onRead();
    
    /**
     * Called by loop when the connection has timed out.
     */
    public function onTimeout();
}
