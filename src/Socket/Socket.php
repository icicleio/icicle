<?php
namespace Icicle\Socket;

use Icicle\Socket\Exception\InvalidArgumentError;
use Icicle\Socket\Exception\FailureException;

abstract class Socket implements SocketInterface
{
    use ParserTrait;

    /**
     * Stream socket resource.
     *
     * @var resource
     */
    private $socket;
    
    /**
     * @param resource $socket PHP stream socket resource.
     *
     * @throws \Icicle\Socket\Exception\InvalidArgumentError If a non-resource is given.
     */
    public function __construct($socket)
    {
        if (!is_resource($socket)) {
            throw new InvalidArgumentError('Non-resource given to constructor!');
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
     * @return bool
     */
    public function isOpen()
    {
        return null !== $this->socket;
    }
    
    /**
     * Closes the socket.
     */
    public function close()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }

        $this->socket = null;
    }
    
    /**
     * Returns the stream socket resource or null if the socket has been closed.
     *
     * @return resource|null
     */
    public function getResource()
    {
        return $this->socket;
    }

    /**
     * Parses the IP address and port of a network socket. Calls stream_socket_get_name() and then parses the returned
     * string.
     *
     * @param bool $peer True for remote IP and port, false for local IP and port.
     *
     * @return array IP address and port pair.
     *
     * @throws \Icicle\Socket\Exception\FailureException If getting the socket name fails.
     */
    protected function getName($peer = true)
    {
        // Error reporting suppressed since stream_socket_get_name() emits an E_WARNING on failure (checked below).
        $name = @stream_socket_get_name($this->socket, (bool) $peer);

        if (false === $name) {
            $message = 'Could not get socket name.';
            if ($error = error_get_last()) {
                $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
            }
            throw new FailureException($message);
        }

        return $this->parseName($name);
    }
}
