<?php
namespace Icicle\Socket;

interface SocketInterface
{
    /**
     * Determines if the socket is still open.
     *
     * @return bool
     *
     * @api
     */
    public function isOpen();
    
    /**
     * Closes the socket, making it unreadable or unwritable.
     *
     * @api
     */
    public function close();
}
