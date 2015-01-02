<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\DeferredPromise;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\InvalidArgumentException;

class LocalClient extends Client
{
    const DEFAULT_CONNECT_TIMEOUT = 30;
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    const DEFAULT_VERIFY_DEPTH = 10;
    
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
     * @param   string $host
     * @param   int $port
     * @param   array $options
     *
     * @return  PromiseInterface Fulfilled with a LocalClient object once the connection is established.
     */
    public static function connect($host, $port, array $options = [])
    {
        if (false !== strpos($host, ':')) {
            $host = '[' . trim($host, '[]') . ']';
        }
        
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
        
        if (null !== $cafile) {
            if (!file_exists($cafile)) {
                return Promise::reject(new InvalidArgumentException('No file exists at path given for cafile.'));
            }
            
            $context['ssl']['cafile'] = $cafile;
        }
        
        $context = stream_context_create($context);
        
        $uri = "tcp://{$host}:{$port}";
        $socket = @stream_socket_client($uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
        
        if (!$socket || $errno) {
            return Promise::reject(new FailureException("Could not connect to {$host}:{$port}; Errno: {$errno}; {$errstr}"));
        }
        
        $deferred = new DeferredPromise();
        
        $await = Loop::await($socket, function () use (&$await, $socket, $deferred) {
            $await->free();
            $deferred->resolve(new static($socket));
        });
        
        $await->listen();
        
        return $deferred->getPromise();
    }
    
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
            $result = @stream_socket_enable_crypto($this->getResource(), true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
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
        
        return $this->await()->then($enable);
    }
    
    /**
     * @return  PromiseInterface Fulfilled when crypto has been disabled.
     */
    public function disableCrypto()
    {
        $start = microtime(true);
        
        $disable = function () use (&$disable, $start) {
            $result = @stream_socket_enable_crypto($this->getResource(), false, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
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
        
        return $this->poll()->then($disable);
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
