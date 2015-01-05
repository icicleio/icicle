<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\Exception\UnavailableException;
use Icicle\Structures\Buffer;
use SplQueue;

class Datagram extends Socket
{
    const CHUNK_SIZE = 8192;
    
    /**
     * @var string
     */
    private $address;
    
    /**
     * @var int
     */
    private $port;
    
    /**
     * @var Deferred|null
     */
    private $deferred;
    
    /**
     * @var PollInterface|null
     */
    private $poll;
    
    /**
     * @var AwaitInterface|null
     */
    private $await;
    
    /**
     * @var SplQueue
     */
    private $writeQueue;
    
    /**
     * @var bool
     */
    private $writable = true;
    
    /**
     * @param   string $host
     * @param   int $port
     * @param   array $options
     */
    public static function create($host, $port, array $options = [])
    {
        if (false !== strpos($host, ':')) { // IPv6 address
            $host = '[' . trim($host, '[]') . ']';
        }
        
        $context = [];
        
        $context['socket'] = [];
        $context['socket']['bindto'] = "{$host}:{$port}";
        
        $context = stream_context_create($context);
        
        $uri = sprintf('udp://%s:%d', $host, $port);
        $socket = @stream_socket_server($uri, $errno, $errstr, STREAM_SERVER_BIND, $context);
        
        if (!$socket || $errno) {
            throw new FailureException("Could not create datagram on {$host}:{$port}: [Errno: {$errno}] {$errstr}");
        }
        
        return new static($socket);
    }
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        stream_set_read_buffer($socket, 0);
        stream_set_write_buffer($socket, 0);
        stream_set_chunk_size($socket, self::CHUNK_SIZE);
        
        list($this->address, $this->port) = self::parseSocketName($socket, false);
        
        $this->writeQueue = new SplQueue();
    }
    
    /**
     * {@inheritdoc}
     */
    public function close(Exception $exception = null)
    {
        if ($this->isOpen()) {
            $this->writable = false;
            
            if (null !== $this->poll) {
                $this->poll->free();
            }
            
            if (null !== $this->await) {
                $this->await->free();
            }
            
            if (null === $exception) {
                $exception = new ClosedException('The connection was closed.');
            }
            
            if (null !== $this->deferred) {
                $this->deferred->reject($exception);
            }
            
            while (!$this->writeQueue->isEmpty()) {
                list( , , , $deferred) = $this->writeQueue->shift();
                $deferred->reject($exception);
            }
        }
        
        parent::close();
    }
    
    /**
     * @return  string
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    /**
     * @return  int
     */
    public function getPort()
    {
        return $this->port;
    }
    
    /**
     * @param   int|null $length
     *
     * @return  PromiseInterface
     */
    public function read($length = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        if (null === $length) {
            $length = self::CHUNK_SIZE;
        } else {
            $length = (int) $length;
            
            if (0 > $length) {
                $length = 0;
            }
        }
        
        $onRead = function ($resource, $expired) use ($length) {
            if ($expired) {
                $this->deferred->reject(new TimeoutException('The datagram timed out.'));
                $this->deferred = null;
                return;
            }
            
            if (@feof($resource)) { // Datagram closed.
                $this->close(new ClosedException('Datagram closed unexpectedly.'));
                return;
            }
            
            $data = @stream_socket_recvfrom($resource, $length, 0, $peer);
            
            if (false === $data) { // Reading failed, so close datagram.
                $this->close(new FailureException('Reading from the datagram failed.'));
                return;
            }
            
            $colon = strrpos($peer, ':');
            
            $address = trim(substr($peer, 0, $colon), '[]');
            $port = (int) substr($peer, $colon + 1);
            
            if (false !== strpos($address, ':')) { // IPv6 address
                $address = '[' . trim($address, '[]') . ']';
            }
            
            $this->deferred->resolve([$address, $port, $data]);
            $this->deferred = null;
        };
        
        if (null === $this->poll) {
            $this->poll = Loop::poll($this->getResource(), $onRead);
        } else {
            $this->poll->setCallback($onRead);
        }
        
        $this->poll->listen();
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * @return  PromiseInterface
     */
    public function poll()
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        $onRead = function ($resource, $expired) {
            if ($expired) {
                $this->deferred->reject(new TimeoutException('The datagram timed out.'));
                $this->deferred = null;
                return;
            }
            
            if (@feof($resource)) { // Datagram closed.
                $this->close(new ClosedException('Datagram closed unexpectedly.'));
                return;
            }
            
            $this->deferred->resolve();
            $this->deferred = null;
        };
        
        if (null === $this->poll) {
            $this->poll = Loop::poll($this->getResource(), $onRead);
        } else {
            $this->poll->setCallback($onRead);
        }
        
        $this->poll->listen();
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * @return  bool
     */
    public function isReadable()
    {
        return $this->isOpen();
    }
    
    /**
     * @param   string $address
     * @param   int $port
     * @param   string|null $data
     */
    public function write($address, $port, $data = null)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        if (is_int($address)) {
            $address = long2ip($address);
        } elseif (false !== strpos($address, ':')) { // IPv6 address
            $address = '[' . trim($address, '[]') . ']';
        }
        
        $data = new Buffer($data);
        $peer = sprintf("%s:%d", $address, $port);
        
        if ($data->isEmpty()) {
            return Promise::resolve(0);
        }
        
        if ($this->writeQueue->isEmpty()) {
            $written = @stream_socket_sendto($this->getResource(), $data->peek(self::CHUNK_SIZE), 0, $peer);
            
            if (false === $written || -1 === $written) {
                $exception = new FailureException("Failed to write to datagram.");
                $this->close($exception);
                return Promise::reject($exception);
            }
            
            if ($data->getLength() === $written) {
                return Promise::resolve($written);
            }
            
            $data->remove($written);
        } else {
            $written = 0;
        }
        
        $deferred = new Deferred();
        $this->writeQueue->push([$data, $written, $peer, $deferred]);
        
        if (null === $this->await) {
            $onWrite = function ($resource) use (&$onWrite) {
                list($data, $previous, $peer, $deferred) = $this->writeQueue->shift();
                
                $written = @stream_socket_sendto($resource, $data->peek(self::CHUNK_SIZE), 0, $peer);
                
                if (false === $written || 0 >= $written) {
                    $exception = new FailureException('Failed to write to datagram.');
                    $deferred->reject($exception);
                    $this->close($exception);
                    return;
                }
                
                $data->remove($written);
                $written += $previous;
                
                if ($data->isEmpty()) {
                    $deferred->resolve($written);
                } else {
                    $this->writeQueue->unshift([$data, $written, $peer, $deferred]);
                }
                
                if (!$this->writeQueue->isEmpty()) {
                    $this->await->listen();
                }
            };
            
            $this->await = Loop::await($this->getResource(), $onWrite);
        }
        
        if (!$this->await->isPending()) {
            $this->await->listen();
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * @return  bool
     */
    public function isWritable()
    {
        return $this->writable;
    }
}
