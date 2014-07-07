<?php
namespace Icicle\PromiseSocket;

use Icicle\Socket\SocketInterface;
use InvalidArgumentException;

abstract class Socket implements SocketInterface
{
    /**
     * @var     resource
     */
    private $socket;
    
    /**
     * Integer identifier based on socket resource. Stored separately so it is still available even after close.
     * @var     int
     */
    private $id;
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        if (!is_resource($socket)) {
            throw new InvalidArgumentException("Non-resource given to constructor!");
        }
        
        $this->socket = $socket;
        $this->id = (int) $socket;
    }
    
    /**
     * Calls fclose() if not previously called on socket.
     */
    public function __destruct()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
    
    /**
     * Determines if the socket is still open.
     * @return  bool
     */
    public function isOpen()
    {
        return (null !== $this->socket);
    }
    
    /**
     * Disconnects the connection and removes it from the loop.
     */
    public function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
        
        $this->socket = null;
    }
    
    /**
     * Returns socket resource or null if the connection is closed.
     * @return  resource|null
     */
    public function getResource()
    {
        return $this->socket;
    }
    
    /**
     * Integer ID of the socket resource. Retained even after closing.
     *
     * @return  int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * Parses the IP address and port of a network socket. Calls stream_socket_get_name() and then parses
     * the returned string.
     *
     * @param   resource $socket
     * @param   bool $peer True for remote ip and port, false for local ip and port.
     *
     * @return  [string, int]
     */
    public static function parseSocketName($socket, $peer = true)
    {
        $name = stream_socket_get_name($socket, (bool) $peer);
        
        $colon = strrpos($name, ':');
        
        $address = trim(substr($name, 0, $colon), '[]');
        $port = (int) substr($name, $colon + 1);
        
        return [$address, $port];
    }
}
