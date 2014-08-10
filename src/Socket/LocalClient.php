<?php
namespace Icicle\Socket;

use Exception;
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
     * @param   string $host
     * @param   int $port
     * @param   bool $secure Use SSL/TLS
     * @param   float $timeout
     * @param   array $options
     *
     * @return  LocalClient
     */
    public static function create($host, $port, $secure = false, $timeout = self::DEFAULT_TIMEOUT, array $options = [])
    {
        $allowSelfSigned = isset($options['allow_self_signed']) ? (bool) $options['allow_self_signed'] : self::DEFAULT_ALLOW_SELF_SIGNED;
        $timeout = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_CONNECT_TIMEOUT;
        $verifyDepth = isset($options['verify_depth']) ? (int) $options['verify_depth'] : self::DEFAULT_VERIFY_DEPTH;
        $cafile = isset($options['cafile']) ? (string) $options['cafile'] : null;
        $cn = isset($options['cn']) ? (string) $options['cn'] : (string) $host;
        
        if (false !== strpos($host, ':')) {
            $host = '[' . trim($host, '[]') . ']';
        }
        
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
                throw new InvalidArgumentException('No file exists at path given for cafile.');
            }
            
            $context['ssl']['cafile'] = $cafile;
        }
        
        $context = stream_context_create($context);
        
        $uri = "tcp://{$host}:{$port}";
        $socket = @stream_socket_client($uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT, $context);
        
        if (!$socket || $errno) {
            throw new FailureException("Could not connect to {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return new static($socket, $secure, $timeout);
    }
    
    /**
     * @param   resource $socket
     * @param   bool $secure
     * @param   float $timeout
     */
    public function __construct($socket, $secure = false, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($socket, $secure, $timeout);
        
        $start = microtime(true);
        
        if ($secure) {
            $enable = function () use (&$enable, $socket, $host, $port, $start) {
                $result = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                
                if (false === $result) {
                    throw new FailureException("Could not connect to {$host}:{$port}: Failed to enable crypto.");
                }
                
                if (0 === $result) {
                    $this->promise = $this->poll()->then($enable);
                    return $this->promise;
                }
                
                return microtime(true) - $start;
            };
            
            $this->promise = $this->await()->then($enable);
        } else {
            $this->promise = $this->await()->then(function () use ($start) {
                return microtime(true) - $start;
            });
        }
        
        $this->promise->done(
            function () use ($socket) {
                list($this->remoteAddress, $this->remotePort) = static::parseSocketName($socket, true);
                list($this->localAddress, $this->localPort) = static::parseSocketName($socket, false);
            },
            function (Exception $exception) {
                $this->close($exception);
            }
        );
        
/*
        $this->promise = $this->promise->tap(function () use ($socket) {
            list($this->remoteAddress, $this->remotePort) = static::parseSocketName($socket, true);
            list($this->localAddress, $this->localPort) = static::parseSocketName($socket, false);
        });
*/
    }
    
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
