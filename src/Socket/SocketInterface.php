<?php
namespace Icicle\Socket;

interface SocketInterface
{
    /**
     * Determines if the socket is still open.
     *
     * @return  bool
     *
     * @api
     */
    public function isOpen();
    
    /**
     * Closes the socket and removes it from the loop.
     *
     * @api
     */
    public function close();
    
    /**
     * Returns stream resource or null if the socket is closed.
     *
     * @return  resource|null
     */
    public function getResource();
    
    /**
     * Integer ID of the socket resource. Should be retained even after closure.
     *
     * @return  int
     */
    public function getId();
}
