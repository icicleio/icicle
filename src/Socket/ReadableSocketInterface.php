<?php
namespace Icicle\Socket;

interface ReadableSocketInterface extends SocketInterface
{
    const NO_TIMEOUT = 0;
    
    const DEFAULT_TIMEOUT = 60;
    
    /**
     * Returns the number of seconds until the connection times out if no read events have occurred.
     *
     * @return  int Number of seconds until a timeout event is triggered if no data is read. 0 = no timeout.
     *
     * @api
     */
    public function getTimeout();
    
    /**
     * Stops listening for incoming data on the socket.
     *
     * @api
     */
    public function pause();
    
    /**
     * Resumes listening for incoming data on the socket.
     *
     * @api
     */
    public function resume();
    
    /**
     * Determines if the socket is paused.
     *
     * @return  bool
     *
     * @api
     */
    public function isPaused();
    
    /**
     * Called when the connection has data waiting to be read or a connection to be accepted.
     */
    public function onRead();
    
    /**
     * Called by loop when the connection has timed out.
     */
    public function onTimeout();
}
