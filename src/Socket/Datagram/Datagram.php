<?php
namespace Icicle\Socket\Datagram;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\BusyException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\Exception\UnavailableException;
use Icicle\Socket\Socket;
use Icicle\Stream\ParserTrait;
use Icicle\Stream\Structures\Buffer;

class Datagram extends Socket implements DatagramInterface
{
    use ParserTrait;

    /**
     * @var string
     */
    private $address;
    
    /**
     * @var int
     */
    private $port;
    
    /**
     * @var \Icicle\Promise\Deferred|null
     */
    private $deferred;
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface
     */
    private $poll;
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface
     */
    private $await;
    
    /**
     * @var \SplQueue
     */
    private $writeQueue;
    
    /**
     * @var int
     */
    private $length = 0;
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        stream_set_read_buffer($socket, 0);
        stream_set_write_buffer($socket, 0);
        stream_set_chunk_size($socket, self::CHUNK_SIZE);
        
        $this->writeQueue = new \SplQueue();
        
        $this->poll = $this->createPoll($socket);
        
        $this->await = $this->createAwait($socket);
        
        try {
            list($this->address, $this->port) = $this->getName(false);
        } catch (Exception $exception) {
            $this->free($exception);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function close(Exception $exception = null)
    {
        $this->free();
    }

    /**
     * Frees resources associated with the datagram and closes the datagram.
     *
     * @param   \Exception|null $exception Reason for closing the datagram.
     */
    protected function free(Exception $exception = null)
    {
        if (null !== $this->poll) {
            $this->poll->free();
            $this->poll = null;
        }

        if (null !== $this->await) {
            $this->await->free();
            $this->await = null;
        }

        if (null !== $this->deferred) {
            if (null === $exception) {
                $exception = new ClosedException('The stream was unexpectedly closed.');
            }

            $this->deferred->reject($exception);
            $this->deferred = null;
        }

        if (!$this->writeQueue->isEmpty()) {
            if (null === $exception) {
                $exception = new ClosedException('The stream was unexpectedly closed.');
            }

            do {
                /** @var \Icicle\Promise\Deferred $deferred */
                list( , , , $deferred) = $this->writeQueue->shift();
                $deferred->reject($exception);
            } while (!$this->writeQueue->isEmpty());
        }

        parent::close();
    }
    
    /**
     * @inheritdoc
     */
    public function getAddress()
    {
        return $this->address;
    }
    
    /**
     * @inheritdoc
     */
    public function getPort()
    {
        return $this->port;
    }
    
    /**
     * @inheritdoc
     */
    public function receive($length = null, $timeout = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on datagram.'));
        }
        
        if (!$this->isOpen()) {
            return Promise::reject(new UnavailableException('The datagram is no longer readable.'));
        }

        $this->length = $this->parseLength($length);
        if (null === $length) {
            $this->length = self::CHUNK_SIZE;
        }
        
        $this->poll->listen($timeout);
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * @inheritdoc
     */
    public function poll($timeout = null)
    {
        return $this->receive(0, $timeout);
    }
    
    /**
     * @inheritdoc
     */
    public function send($address, $port, $data)
    {
        if (!$this->isOpen()) {
            return Promise::reject(new UnavailableException('The datagram is no longer writable.'));
        }
        
        $data = new Buffer($data);
        $written = 0;
        $peer = $this->makeName($address, $port);
        
        if ($this->writeQueue->isEmpty()) {
            if ($data->isEmpty()) {
                return Promise::resolve($written);
            }
            
            $written = stream_socket_sendto($this->getResource(), $data->peek(self::CHUNK_SIZE), 0, $peer);
            
            // Having difficulty finding a test to cover this scenario, but the check seems appropriate.
            if (false === $written || -1 === $written) {
                $message = 'Failed to write to datagram.';
                if (null !== ($error = error_get_last())) {
                    $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
                }
                $exception = new FailureException($message);
                $this->free($exception);
                return Promise::reject($exception);
            }
            
            if ($data->getLength() <= $written) {
                return Promise::resolve($written);
            }
            
            $data->remove($written);
        }
        
        $deferred = new Deferred();
        $this->writeQueue->push([$data, $written, $peer, $deferred]);
        
        if (!$this->await->isPending()) {
            $this->await->listen();
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * @inheritdoc
     */
    public function await()
    {
        if (!$this->isOpen()) {
            return Promise::reject(new UnavailableException('The datagram is no longer writable.'));
        }
        
        $deferred = new Deferred();
        $this->writeQueue->push([new Buffer(), 0, null, $deferred]);
        
        if (!$this->await->isPending()) {
            $this->await->listen();
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    protected function createPoll($socket)
    {
        return Loop::poll($socket, function ($resource, $expired) {
            if ($expired) {
                $this->deferred->reject(new TimeoutException('The datagram timed out.'));
                $this->deferred = null;
                return;
            }
            
            if (0 === $this->length) {
                $this->deferred->resolve([null, null, '']);
                $this->deferred = null;
                return;
            }
            
            $data = stream_socket_recvfrom($resource, $this->length, 0, $peer);
            
            // Having difficulty finding a test to cover this scenario, but the check seems appropriate.
            if (false === $data) { // Reading failed, so close datagram.
                $message = 'Failed to read from datagram.';
                if (null !== ($error = error_get_last())) {
                    $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
                }
                $this->free(new FailureException($message));
                return;
            }
            
            list($address, $port) = $this->parseName($peer);
            
            $this->deferred->resolve([$address, $port, $data]);
            $this->deferred = null;
        });
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    protected function createAwait($socket)
    {
        return Loop::await($socket, function ($resource) use (&$onWrite) {
            /**
             * @var \Icicle\Stream\Structures\Buffer $data
             * @var \Icicle\Promise\Deferred $deferred
             */
            list($data, $previous, $peer, $deferred) = $this->writeQueue->shift();
            
            if ($data->isEmpty()) {
                $deferred->resolve($previous);
            } else {
                $written = stream_socket_sendto($resource, $data->peek(self::CHUNK_SIZE), 0, $peer);
                
                // Having difficulty finding a test to cover this scenario, but the check seems appropriate.
                if (false === $written || 0 >= $written) {
                    $message = 'Failed to write to datagram.';
                    if (null !== ($error = error_get_last())) {
                        $message .= sprintf(' Errno: %d; %s', $error['type'], $error['message']);
                    }
                    $exception = new FailureException($message);
                    $deferred->reject($exception);
                    $this->free($exception);
                    return;
                }
                
                if ($data->getLength() <= $written) {
                    $deferred->resolve($written + $previous);
                } else {
                    $data->remove($written);
                    $written += $previous;
                    $this->writeQueue->unshift([$data, $written, $peer, $deferred]);
                }
            }
            
            if (!$this->writeQueue->isEmpty()) {
                $this->await->listen();
            }
        });
    }
}
