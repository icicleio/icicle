<?php
namespace Icicle\PromiseSocket;

use InvalidArgumentException;
use LogicException;
use RuntimeException;

use Icicle\Loop\Loop;
use Icicle\Promise\DeferredPromise;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\SocketException;
use Icicle\Socket\ReadableSocketInterface;

class Server extends Socket implements ReadableSocketInterface
{
    const DEFAULT_QUEUE_LENGTH = 100;
    
    const DEFAULT_ACCEPT_TIMEOUT = 1;
    
    /**
     * Listening hostname or IP address.
     * @var     string
     */
    private $host;
    
    /**
     * Listening port.
     * @var     int
     */
    private $port;
    
    /**
     * True if using SSL/TLS, false otherwise.
     * @var     bool
     */
    private $secure;
    
    /**
     * Number of seconds to attempt to accept a client.
     * @var     int
     */
    private $acceptTimeout;
    
    /**
     * @var     DeferredPromise
     */
    private $deferred;
    
    /**
     * @var     Client|null
     */
    private $client;
    
    /**
     * @param   string $host
     * @param   int $port
     * @param   string|null $pem
     * @param   string|null $passphrase
     * @param   array $options
     * @return  Server
     */
    public static function create($host, $port, array $options = null)
    {
        if (!is_int($port) || 0 >= $port) {
            throw new InvalidArgumentException('The port must be a positive integer.');
        }
        
        $queue = isset($options['queue_length']) ? (int) $options['queue_length'] : self::DEFAULT_QUEUE_LENGTH;
        $acceptTimeout = isset($options['accept_timeout']) ? (float) $options['accept_timeout'] : self::DEFAULT_ACCEPT_TIMEOUT;
        $pem = isset($options['pem']) ? (string) $options['pem'] : null;
        $passphrase = isset($options['passphrase']) ? (string) $options['passphrase'] : null;
        
        if (false !== strpos($host, ':')) {
            $host = '[' . trim($host, '[]') . ']';
        }
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = sprintf('%s:%d', $host, $port);
        $context['socket']['backlog'] = $queue;
        
        if (is_string($pem)) {
            if (!file_exists($pem)) {
                throw new InvalidArgumentException('No file found at given PEM path.');
            }
            
            $secure = true;
            
            $context['ssl'] = [];
            $context['ssl']['local_cert'] = $pem;
            $context['ssl']['disable_compression'] = true;
            
            if (is_string($passphrase)) {
                $context['ssl']['passphrase'] = $passphrase;
            }
        } else {
            $secure = false;
        }
        
        $context = stream_context_create($context);
        
        $uri = sprintf('%s://%s:%d', ($secure ? 'tls' : 'tcp'), $host, $port);
        
        $socket = stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $context);
        
        if (!$socket || $errno) {
            throw new RuntimeException("Could not create server ({$uri}): [{$errno}] {$errstr}.");
        }
        
        return new static($socket, $secure, $acceptTimeout);
    }
    
    /**
     * @param   resource $socket
     * @param   LoopInterface $loop
     * @param   bool $secure
     */
    public function __construct($socket, $secure, $acceptTimeout = self::DEFAULT_ACCEPT_TIMEOUT)
    {
        parent::__construct($socket);
        
        $this->secure = (bool) $secure;
        $this->acceptTimeout = (float) $acceptTimeout;
        
        if (0 >= $this->acceptTimeout) {
            throw new InvalidArgumentException('The accept timeout must be a positive integer or float.');
        }
        
        stream_set_blocking($socket, 0);
        
        list($this->host, $this->port) = self::parseSocketName($socket, false);
        
        Loop::getInstance()->addReadableSocket($this);
    }
    
    public function close()
    {
        if ($this->isOpen()) {
            Loop::getInstance()->removeSocket($this);
            parent::close();
        }
    }
    
    /**
     * Determines if the server is using SSL/TLS for connections.
     * @return  bool
     */
    public function isSecure()
    {
        return $this->secure;
    }
    
    /**
     * Returns the hostname or IP addresses on which the server is listening.
     * @return  string
     */
    public function getHost()
    {
        return $this->host;
    }
    
    /**
     * Returns the port on which the server is listening.
     * @return  int
     */
    public function getPort()
    {
        return $this->port;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getHost() . ':' . $this->getPort();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPaused()
    {
        return !Loop::getInstance()->isReadableSocketPending($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pause()
    {
        Loop::getInstance()->removeSocket($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function resume()
    {
        Loop::getInstance()->addReadableSocket($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function onRead()
    {
        if ($this->isOpen()) {
            $client = @stream_socket_accept($this->getResource(), $this->acceptTimeout);
            
            if ($client) {
                $client = new Client($client, $this->secure);
                
                if (null !== $this->deferred) {
                    $this->deferred->resolve($client);
                    $this->deferred = null;
                } else {
                    $this->client = $client;
                    $this->pause();
                    //echo "Pausing server.\n";
                }
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function onTimeout()
    {
        if (null !== $this->deferred) {
            $this->deferred->reject(new SocketException('The connection timed out.'));
            $this->deferred = null;
        } else {
            $this->close();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTimeout()
    {
        return self::NO_TIMEOUT;
    }
    
    /**
     * Accepts incoming client connections. Should be called in response to a 'ready' event (blocking read).
     * @return  Client|null
     */
    public function accept()
    {
        if (null !== $this->deferred) {
            return Promise::reject(new SocketException('Already waiting on server.'));
        }
        
        if (null !== $this->client) {
            $promise = Promise::resolve($this->client);
            $this->client = null;
            if ($this->isPaused()) {
                $this->resume();
                //echo "Resuming server.\n";
            }
            return $promise;
        }
        
        if (!$this->isOpen()) {
            return Promise::reject(new SocketException('The server has disconnected.'));
        }
        
        $this->deferred = new DeferredPromise();
        return $this->deferred->getPromise();
    }
    
    /**
     * Generates a self-signed certificate and private key that can be used for testing a server.
     * @param   string $country Country (2 letter code)
     * @param   string $state State or Province
     * @param   string $city Locality (eg, city)
     * @param   string $company Organization Name (eg, company)
     * @param   string $section Organizational Unit (eg, section)
     * @param   string $domain Common Name (domain name)
     * @param   string $email Email Address
     * @param   string $passphrase Optional passphrase, NULL for none.
     * @param   string $path Path to write PEM file. If NULL, the PEM is returned.
     * @return  string|int|bool Returns the PEM if $path was NULL, or the number of bytes written to $path, or false if the file
     *          could not be written.
     */
    public static function generateCert($country, $state, $city, $company, $section, $domain, $email, $passphrase = NULL, $path = NULL)
    {
        if (!extension_loaded('openssl')) {
            throw new LogicException('The OpenSSL extension must be loaded to create a certificate.');
        }
        
        $dn = [
            'countryName' => $country,
            'stateOrProvinceName' => $state,
            'localityName' => $city,
            'organizationName' => $company,
            'organizationalUnitName' => $section,
            'commonName' => $domain,
            'emailAddress' => $email
        ];
        
        $privkey = openssl_pkey_new();
        $cert = openssl_csr_new($dn, $privkey);
        $cert = openssl_csr_sign($cert, null, $privkey, 365);
        
        $pem = [];
        openssl_x509_export($cert, $pem[0]);
        
        if (!is_null($passphrase)) {
            openssl_pkey_export($privkey, $pem[1], $passphrase);
        } else {
            openssl_pkey_export($privkey, $pem[1]);
        }
        
        $pem = implode($pem);
        
        if (is_null($path)) {
            return $pem;
        } else {
            return file_put_contents($path, $pem);
        }
    }
}
