<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\BusyException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;

class RemoteClient extends Client
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
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        list($this->remoteAddress, $this->remotePort) = static::parseSocketName($socket, true);
        list($this->localAddress, $this->localPort) = static::parseSocketName($socket, false);
    }
    
    /**
     * @return  PromiseInterface Fulfilled when crypto has been enabled.
     */
    public function enableCrypto()
    {
        $start = microtime(true);
        
        $enable = function () use (&$enable, $start) {
            $result = @stream_socket_enable_crypto($this->getResource(), true, STREAM_CRYPTO_METHOD_TLS_SERVER);
            
            if (false === $result) {
                $message = 'Failed to enable crypto';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= "; Errno: {$error['type']}; {$error['message']}";
                }
                throw new FailureException($message);
            }
            
            if (0 === $result) {
                return $this->poll()->then($enable);
            }
            
            return microtime(true) - $start;
        };
        
        return $this->poll()->then($enable);
    }
    
    /**
     * @return  PromiseInterface Fulfilled when crypto has been disabled.
     */
    public function disableCrypto()
    {
        $start = microtime(true);
        
        $disable = function () use (&$disable, $start) {
            $result = @stream_socket_enable_crypto($this->getResource(), false, STREAM_CRYPTO_METHOD_TLS_SERVER);
            
            if (false === $result) {
                $message = 'Failed to disable crypto';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= "; Errno: {$error['type']}; {$error['message']}";
                }
                throw new FailureException($message);
            }
            
            if (0 === $result) {
                return $this->poll()->then($disable);
            }
            
            return microtime(true) - $start;
        };
        
        return $this->await()->then($disable);
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
