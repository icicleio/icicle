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
     * Closes the socket.
     *
     * @api
     */
    public function close();
    
    /**
     * Returns socket resource.
     *
     * @return  resource
     */
    public function getResource();
    
    /**
     * Integer ID of the socket resource.
     *
     * @return  int
     */
    public function getId();
}
