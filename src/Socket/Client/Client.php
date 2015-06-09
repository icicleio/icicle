<?php
namespace Icicle\Socket\Client;

use Exception;
use Icicle\Socket\Stream\DuplexStream;
use Icicle\Socket\Exception\FailureException;

class Client extends DuplexStream implements ClientInterface
{
    /**
     * @var int
     */
    private $crypto = 0;
    
    /**
     * @var string
     */
    private $remoteAddress;
    
    /**
     * @var int
     */
    private $remotePort;
    
    /**
     * @var string
     */
    private $localAddress;
    
    /**
     * @var int
     */
    private $localPort;
    
    /**
     * @param resource $socket Stream socket resource.
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        try {
            list($this->remoteAddress, $this->remotePort) = $this->getName(true);
            list($this->localAddress, $this->localPort) = $this->getName(false);
        } catch (Exception $exception) {
            $this->free($exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function enableCrypto($method, $timeout = null)
    {
        $method = (int) $method;
        
        $enable = function () use (&$enable, $method, $timeout) {
            // Error report suppressed since stream_socket_enable_crypto() emits an E_WARNING on failure (checked below).
            $result = @stream_socket_enable_crypto($this->getResource(), true, $method);
            
            if (false === $result) {
                $message = 'Failed to enable crypto.';
                if (null !== ($error = error_get_last())) {
                    $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
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
     * @return bool
     */
    public function isCryptoEnabled()
    {
        return 0 !== $this->crypto;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRemoteAddress()
    {
        return $this->remoteAddress;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getRemotePort()
    {
        return $this->remotePort;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLocalAddress()
    {
        return $this->localAddress;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLocalPort()
    {
        return $this->localPort;
    }
}
