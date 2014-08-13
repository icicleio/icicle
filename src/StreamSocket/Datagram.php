<?php
namespace Icicle\StreamSocket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\DeferredPromise;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\AcceptException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\Exception\UnavailableException;
use Icicle\Socket\DuplexSocketInterface;
use Icicle\Structures\Buffer;
use SplQueue;

class Datagram extends Socket implements DuplexSocketInterface
{
    const CHUNK_SIZE = 8192;
    
    private $address;
    
    private $port;
    
    private $deferred;
    
    private $length;
    
    private $writeQueue;
    
    private $writable = true;
    
    public function __construct($host, $port, array $options = [])
    {
        $context = [];
        
        $context = stream_context_create($context);
        
        $uri = "udp://{$host}:{$port}";
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $context);
        
        if (!$socket || $errno) {
            throw new FailureException("Could not create server {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        parent::__construct($socket);
        
        stream_set_blocking($socket, 0);
        
        list($this->address, $this->port) = self::parseSocketName($socket, false);
        
        $this->writeQueue = new SplQueue();
    }
    
    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->isOpen()) {
            if (null !== $this->deferred) {
                $this->deferred->reject(new ClosedException('The server has closed.'));
                $this->deferred = null;
            }
        
            Loop::getInstance()->removeSocket($this);
        }
        
        parent::close();
    }
    
    /**
     * Returns the hostname or IP addresses on which the server is listening.
     * @return  string
     */
    public function getAddresss()
    {
        return $this->address;
    }
    
    /**
     * Returns the port on which the server is listening.
     * @return  int
     */
    public function getPort()
    {
        return $this->port;
    }
    
    public function read($length = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException("Already waiting on datagram."));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new ClosedExceptoin("The datagram has closed."));
        }
        
        if (null !== $length) {
            $length = (int) $length;
            
            if (0 > $length) {
                $length = 0;
            }
        }
        
        Loop::getInstance()->scheduleReadableSocket($this);
        
        $this->length = $length;
        
        $this->deferred = new DeferredPromise(function () {
            Loop::getInstance()->unscheduleReadableSocket($this);
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    public function isReadable()
    {
        return $this->isOpen();
    }
    
    public function write($address, $port, $data = null)
    {
        if (is_int($address)) {
            $address = long2ip($address);
        } elseif (false !== strpos($address, ':')) {
            $address = '[' . trim($address, '[]') . ']';
        }
        
        $peer = "{$address}:{$port}";
        
        $data = new Buffer($data);
        
        $result = @stream_socket_sendto($this->getResource(), $data, 0, $peer);
        
        echo "Write result: {$result}\n";
    }
    
    public function onRead()
    {
        $socket = $this->getResource();
        
        if (@feof($socket)) {
            if (null !== $this->deferred) {
                $this->deferred->reject(new ClosedException('Connection reset by peer.'));
                $this->deferred = null;
            }
            $this->close();
            return;
        }
        
        if (null === $this->length) {
            $length = self::CHUNK_SIZE;
        } else {
            $length = $this->length;
        }
        
        if (0 === $length) {
            $data = '';
        } else {
            $data = @stream_socket_recvfrom($socket, $length, 0, $peer);
            
            if (false === $data) { // Reading failed, so close connection.
                if (null !== $this->deferred) {
                    $this->deferred->reject(new FailureException('Reading from the socket failed.'));
                    $this->deferred = null;
                }
                $this->close();
                return;
            }
        }
        
        $colon = strrpos($peer, ':');
        
        $address = trim(substr($peer, 0, $colon), '[]');
        $port = (int) substr($peer, $colon + 1);
        
        if (false !== strpos($address, ':')) {
            $address = '[' . trim($address, '[]') . ']';
        }
        
        $this->deferred->resolve([$address, $port, $data]);
        $this->deferred = null;
    }
    
    public function onTimeout()
    {
        
    }
    
    public function onWrite()
    {
        
    }
    
    public function getTimeout()
    {
        return self::NO_TIMEOUT;
    }
}
