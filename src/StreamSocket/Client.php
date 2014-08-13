<?php
namespace Icicle\StreamSocket;

use Exception;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\InvalidArgumentException;

abstract class Client extends Stream
{
    /**
     * Determines if the connection is secure (using SSL/TLS).
     *
     * @var     bool
     */
    private $secure;
    
    /**
     * @return  PromiseInterface Promise that is fulfilled with the Client object when the client is ready to be used.
     */
    abstract public function ready();
    
    /**
     * Remote IP address (as an int).
     *
     * @return  int
     */
    abstract public function getRemoteAddress();
    
    /**
     * Remote port number.
     *
     * @return  int
     */
    abstract public function getRemotePort();
    
    /**
     * Local IP address (as an int).
     *
     * @return  int
     */
    abstract public function getLocalAddress();
    
    /**
     * Local port number.
     *
     * @return  int
     */
    abstract public function getLocalPort();
    
    /**
     * @param   resource $socket
     * @param   bool $secure
     * @param   int $timeout
     */
    public function __construct($socket, $secure, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($socket, $timeout);
        
        $this->secure = (bool) $secure;
    }
    
    /**
     * @return  bool
     */
    public function isSecure()
    {
        return $this->secure;
    }
}
