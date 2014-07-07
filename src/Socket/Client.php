<?php
namespace Icicle\PromiseSocket;

use InvalidArgumentException;

use Icicle\Socket\Exception\SocketException;
use Icicle\Stream\ReadableStreamInterface;

class Client extends Stream
{
    const DEFAULT_CONNECT_TIMEOUT = 5;
    
    const DEFAULT_ALLOW_SELF_SIGNED = false;
    
    const DEFAULT_VERIFY_DEPTH = 10;
    
    /**
     * Local IP as a string.
     * @var     string
     */
    private $localAddress;
    
    /**
     * Local port.
     * @var     int
     */
    private $localPort;
    
    /**
     * Remote IP as a string.
     * @var     string
     */
    private $remoteAddress;
    
    /**
     * Remote port.
     * @var     int
     */
    private $remotePort;
    
    /**
     * Determines if the connection is secure (using SSL).
     * @var     bool
     */
    private $secure;
    
    /**
     * Connects to a server, returning a Client object.
     * @param   string $host
     * @param   int $port
     * @param   bool $secure
     * @return  Client
     * @throws  ConnectionException
     */
    public static function create($host, $port, $secure = false, array $options = [])
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
        $context['socket']['connect'] = sprintf('%s:%d', $host, $port);
        
        if ($secure) {
            $context['ssl'] = [];
            
            $context['ssl']['capture_peer_cert'] = true;
            $context['ssl']['capture_peer_chain'] = true;
            $context['ssl']['capture_peer_cert_chain'] = true;
            
            $context['ssl']['verify_peer'] = true;
            $context['ssl']['allow_self_signed'] = $allowSelfSigned;
            $context['ssl']['verify_depth'] = $verifyDepth;
            
            $context['ssl']['CN_match'] = $cn;
            $context['ssl']['disable_compression'] = true;
            
            if (is_string($cafile)) {
                if (!file_exists($cafile)) {
                    throw new InvalidArgumentException('No file exists at cafile path given.');
                }
                
                $context['ssl']['cafile'] = $cafile;
            }
        }
        
        $context = stream_context_create($context);
        
        $uri = sprintf('%s://%s:%d', ($secure ? 'tls' : 'tcp'), $host, $port);
        
        $socket = @stream_socket_client($uri, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
        
        if (!$socket || $errno) {
            throw new SocketException("Could not connect to server ({$uri}): [Errno: {$errno}] Message: '{$errstr}'");
        }
        
        return new static($socket, $secure, self::NO_TIMEOUT);
    }
    
    /**
     * @param   resource $socket
     * @param   bool $secure
     * @param   int $timeout
     */
    public function __construct($socket, $secure, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($socket, $timeout);
        
        $this->secure = (bool) $secure;
        
        list($this->remoteAddress, $this->remotePort) = self::parseSocketName($socket, true);
        list($this->localAddress, $this->localPort) = self::parseSocketName($socket, false);
    }
    
    /**
     * @return  bool
     */
    public function isSecure()
    {
        return $this->secure;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getRemoteAddress() . ':' . $this->getRemotePort();
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
