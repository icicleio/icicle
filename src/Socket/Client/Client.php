<?php
namespace Icicle\Socket\Client;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Stream\DuplexStream;
use Icicle\Socket\Exception\FailureException;

class Client extends DuplexStream implements ClientInterface
{
    /**
     * @var int
     */
    private $crypto = 0;
    
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
            list($this->remoteAddress, $this->remotePort) = $this->getName(true);
            list($this->localAddress, $this->localPort) = $this->getName(false);
        } catch (Exception $exception) {
            $this->close($exception);
        }
    }
    
    /**
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER for incoming (remote)
     *          clients, STREAM_CRYPTO_METHOD_TLS_CLIENT for outgoing (local) clients.
     * @param   int|float|null $timeout Seconds to wait between reads/writes to enable crypto before failing.
     *
     * @return  PromiseInterface
     *
     * @resolve $this
     *
     * @reject  FailureException If enabling crypto fails.
     * @reject  ClosedException If the client has been closed.
     * @reject  BusyException If the client was already busy waiting to read.
     */
    public function enableCrypto($method = STREAM_CRYPTO_METHOD_TLS_SERVER, $timeout = null)
    {
        $method = (int) $method;
        
        $enable = function () use (&$enable, $method, $timeout) {
            $result = @stream_socket_enable_crypto($this->getResource(), true, $method);
            
            if (false === $result) {
                $message = 'Failed to enable crypto.';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= " Errno: {$error['type']}; {$error['message']}";
                }
                throw new FailureException($message);
            }
            
            if (0 === $result) {
                return $this->poll($timeout)->then($enable);
            }
            
            $this->crypto = $method;
            
            return $this;
        };
        
        return $this->await($timeout)->then($enable);
    }
    
    /**
     * @return  bool
     */
    public function isCryptoEnabled()
    {
        return 0 !== $this->crypto;
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
