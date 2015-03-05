<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\TimeoutException;

class Client extends DuplexStream implements ClientInterface
{
    const DEFAULT_CONNECT_TIMEOUT = 30;
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    const DEFAULT_VERIFY_DEPTH = 10;
    const DEFAULT_PROTOCOL = 'tcp';
    
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
     * @param   string $host Hostname or IP address.
     * @param   int $port Port number.
     * @param   array $options
     *
     * @return  PromiseInterface Fulfilled with a LocalClient object once the connection is established.
     *
     * @resolve LocalClient
     *
     * @reject  FailureException If connecting fails.
     * @reject  InvalidArgumentException If a CA file does not exist at the path given.
     */
    public static function connect($host, $port, array $options = null)
    {
        if (false !== strpos($host, ':')) {
            $host = '[' . trim($host, '[]') . ']';
        }
        
        $protocol = isset($options['protocol']) ? (string) $options['protocol'] : self::DEFAULT_PROTOCOL;
        $allowSelfSigned = isset($options['allow_self_signed']) ? (bool) $options['allow_self_signed'] : self::DEFAULT_ALLOW_SELF_SIGNED;
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_CONNECT_TIMEOUT;
        $verifyDepth = isset($options['verify_depth']) ? (int) $options['verify_depth'] : self::DEFAULT_VERIFY_DEPTH;
        $cafile = isset($options['cafile']) ? (string) $options['cafile'] : null;
        $cn = isset($options['cn']) ? (string) $options['cn'] : (string) $host;
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['connect'] = "{$host}:{$port}";
        
        $context['ssl'] = [];
        $context['ssl']['capture_peer_cert'] = true;
        $context['ssl']['capture_peer_chain'] = true;
        $context['ssl']['capture_peer_cert_chain'] = true;
        
        $context['ssl']['verify_peer'] = true;
        $context['ssl']['allow_self_signed'] = $allowSelfSigned;
        $context['ssl']['verify_depth'] = $verifyDepth;
        
        $context['ssl']['CN_match'] = $cn;
        $context['ssl']['disable_compression'] = true;
        
        // @codeCoverageIgnoreStart
        if (null !== $cafile) {
            if (!file_exists($cafile)) {
                return Promise::reject(new InvalidArgumentException('No file exists at path given for cafile.'));
            }
            $context['ssl']['cafile'] = $cafile;
        } // @codeCoverageIgnoreEnd
        
        $context = stream_context_create($context);
        
        $uri = sprintf('%s://%s:%d', $protocol, $host, $port);
        $socket = @stream_socket_client($uri, $errno, $errstr, null, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
        
        if (!$socket || $errno) {
            return Promise::reject(new FailureException("Could not connect to {$uri}; Errno: {$errno}; {$errstr}"));
        }
        
        return new Promise(function ($resolve, $reject) use ($socket, $timeout) {
            $await = Loop::await($socket, function ($resource, $expired) use (&$await, $resolve, $reject) {
                $await->free();
                
                if ($expired) {
                    $reject(new TimeoutException('Connection attempt timed out.'));
                } else {
                    $resolve(new static($resource));
                }
            });
            
            $await->listen($timeout);
        });
    }
    
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
     * @param   int $method One of the server crypto flags, e.g. STREAM_CRYPTO_METHOD_TLS_SERVER for incoming (remote)
     *          clients, STREAM_CRYPTO_METHOD_TLS_CLIENT for outgoing (local) clients.
     *
     * @return  PromiseInterface
     *
     * @resolve self
     *
     * @reject  FailureException If enabling crypto fails.
     * @reject  ClosedException If the client has been closed.
     * @reject  BusyException If the client was already busy waiting to read.
     */
    public function enableCrypto($method = STREAM_CRYPTO_METHOD_TLS_SERVER)
    {
        $method = (int) $method;
        
        $enable = function () use (&$enable, $method) {
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
                return $this->poll()->then($enable);
            }
            
            $this->crypto = $method;
            
            return $this;
        };
        
        return $this->await()->then($enable);
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
