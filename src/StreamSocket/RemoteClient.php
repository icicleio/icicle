<?php
namespace Icicle\StreamSocket;

use Exception;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\BusyException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;

class RemoteClient extends Client
{
    /**
     * @var     PromiseInterface
     */
    private $promise;
    
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
     * @param   bool $secure
     * @param   float $timeout
     */
    public function __construct($socket, $secure = false, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($socket, $secure, $timeout);
        
        if ($secure) {
            $start = microtime(true);
            $enable = function () use (&$enable, $socket, $start) {
                $result = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_SERVER);
                
                if (false === $result) {
                    throw new FailureException('Failed to enable crypto.');
                }
                
                if (0 === $result) {
                    $this->promise = $this->poll()->then($enable);
                    return $this->promise;
                }
                
                return microtime(true) - $start;
            };
            
            $this->promise = $this->poll()->then($enable);
            
            $this->promise->otherwise(function (Exception $exception) {
                $this->close($exception);
            });
        } else {
            $this->promise = Promise::resolve(0);
        }
        
        list($this->remoteAddress, $this->remotePort) = static::parseSocketName($socket, true);
        list($this->localAddress, $this->localPort) = static::parseSocketName($socket, false);
    }
    
    /**
     * @return  PromiseInterface
     */
    public function ready()
    {
        return $this->promise;
        
/*
        if (null !== $this->promise) {
            return $this->promise;
        }
        
        if ($this->isOpen()) {
            return Promise::resolve($this);
        }
        
        return Promise::reject(new ClosedException('The client disconnected.'));
*/
    }
    
/*
    public function read($length = null)
    {
        if (null !== $this->promise) {
            return Promise::reject(new BusyException('Client is not ready, call ready() before read().'));
        }
        
        return parent::read($length);
    }
    
    public function write($data = null)
    {
        if (null !== $this->promise) {
            return Promise::reject(new BusyException('Client is not ready, call ready() before write().'));
        }
        
        return parent::write($data);
    }
*/
    
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
