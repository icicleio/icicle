<?php
namespace Icicle\Socket;

interface SocketInterface
{
    const CHUNK_SIZE = 8192; // 8kB
    
    /**
     * Determines if the socket is still open.
     *
     * @return bool
     */
    public function isOpen();
    
    /**
     * Closes the socket, making it unreadable or unwritable.
     */
    public function close();
}
