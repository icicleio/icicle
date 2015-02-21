<?php
namespace Icicle\Socket;

abstract class Client extends DuplexStream
{
    /**
     * @var     int
     */
    private $remoteAddress = 0;
    
    /**
     * @var     int
     */
    private $remotePort = 0;
    
    /**
     * @var     int
     */
    private $localAddress = 0;
    
    /**
     * @var     int
     */
    private $localPort = 0;
    
    /**
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER
     *
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while enabling crypto.
     */
    abstract public function enableCrypto($method);
    
    /**
     * @return  PromiseInterface Fulfilled with the number of seconds elapsed while disabling crypto.
     */
    abstract public function disableCrypto();
    
    /**
     * @return  bool
     */
    abstract public function isCryptoEnabled();
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        list($this->remoteAddress, $this->remotePort) = static::parseSocketName($socket, true);
        list($this->localAddress, $this->localPort) = static::parseSocketName($socket, false);
    }
    
    /**
     * Returns the remote IP as a string representation, such as '127.0.0.1'.
     * @return  string
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }
    
    /**
     * Returns the remote port number.
     * @return  int
     */
    public function getRemotePort()
    {
        return $this->remotePort;
    }
    
    /**
     * Returns the remote IP as a string representation, such as '127.0.0.1'.
     * @return  string
     */
    public function getLocalAddress()
    {
        return $this->localAddress;
    }
    
    /**
     * Returns the local port number.
     * @return  int
     */
    public function getLocalPort()
    {
        return $this->remotePort;
    }
}
