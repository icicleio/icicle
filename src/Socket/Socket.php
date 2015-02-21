<?php
namespace Icicle\Socket;

use Icicle\Socket\Exception\InvalidArgumentException;

abstract class Socket
{
    const CHUNK_SIZE = 8192;
    
    /**
     * @var     resource
     */
    private $socket;
    
    /**
     * @param   resource $socket PHP stream socket resource.
     *
     * @throws  InvalidArgumentException Thrown if a non-resource is given.
     */
    public function __construct($socket)
    {
        if (!is_resource($socket)) {
            throw new InvalidArgumentException('Non-resource given to constructor!');
        }
        
        $this->socket = $socket;
        
        stream_set_blocking($this->socket, 0);
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
     *
     * @return  bool
     */
    public function isOpen()
    {
        return is_resource($this->socket);
    }
    
    /**
     * Closes the socket.
     */
    public function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
    
    /**
     * Returns the stream socket resource.
     *
     * @return  resource
     */
    public function getResource()
    {
        return $this->socket;
    }
    
    /**
     * Integer ID of the stream socket resource.
     *
     * @return  int
     */
    public function getId()
    {
        return (int) $this->socket;
    }
    
    /**
     * Parses the IP address and port of a network socket. Calls stream_socket_get_name() and then parses
     * the returned string.
     *
     * @param   resource $socket
     * @param   bool $peer True for remote ip and port, false for local ip and port.
     *
     * @return  [int, int]
     *
     * @throws  FailureException Thrown if getting the socket name fails.
     */
    public static function parseSocketName($socket, $peer = true)
    {
        $name = @stream_socket_get_name($socket, (bool) $peer);
        
        if (false === $name) {
            $error = error_get_last();
            $message = 'Could not get socket name';
            if (null !== $error) {
                $message .= "; Errno: {$error['type']}; {$error['message']}";
            }
            throw new InvalidArgumentException($message);
        }
        
        $colon = strrpos($name, ':');
        
        $address = trim(substr($name, 0, $colon), '[]');
        $port = (int) substr($name, $colon + 1);
        
        if (false !== strpos($address, ':')) { // IPv6 address
            $address = '[' . trim($address, '[]') . ']';
        }
        
        return [$address, $port];
    }
}
