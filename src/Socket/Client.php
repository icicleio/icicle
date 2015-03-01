<?php
namespace Icicle\Socket;

use Exception;

abstract class Client extends DuplexStream implements ClientInterface
{
    /**
     * @var int
     */
    private $remoteAddress = 0;
    
    /**
     * @var int
     */
    private $remotePort = 0;
    
    /**
     * @var int
     */
    private $localAddress = 0;
    
    /**
     * @var int
     */
    private $localPort = 0;
    
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        try {
            list($this->remoteAddress, $this->remotePort) = static::parseSocketName($socket, true);
            list($this->localAddress, $this->localPort) = static::parseSocketName($socket, false);
        } catch (Exception $exception) {
            $this->close($exception);
        }
    }
    
    /**
     * Returns the remote IP as a string representation.
     *
     * @return  string
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }
    
    /**
     * Returns the remote port number.
     *
     * @return  int
     */
    public function getRemotePort()
    {
        return $this->remotePort;
    }
    
    /**
     * Returns the local IP as a string representation.
     *
     * @return  string
     */
    public function getLocalAddress()
    {
        return $this->localAddress;
    }
    
    /**
     * Returns the local port number.
     *
     * @return  int
     */
    public function getLocalPort()
    {
        return $this->localPort;
    }
}
