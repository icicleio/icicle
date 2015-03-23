<?php
namespace Icicle\Socket\Datagram;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\BusyException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\EofException;
use Icicle\Socket\Exception\InvalidArgumentException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\Exception\UnavailableException;
use Icicle\Socket\Socket;
use Icicle\Stream\Structures\Buffer;
use SplQueue;

class Datagram extends Socket implements DatagramInterface
{
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
     * @var PollInterface
     */
    private $poll;
    
    /**
     * @var AwaitInterface
     */
    private $await;
    
    /**
     * @var SplQueue
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
        
        $this->writeQueue = new SplQueue();
        
        $this->poll = $this->createPoll($socket);
        
        $this->await = $this->createAwait($socket);
        
        try {
            list($this->address, $this->port) = self::parseSocketName($socket, false);
        } catch (Exception $exception) {
            $this->close($exception);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function close(Exception $exception = null)
    {
        $this->poll->free();
        $this->await->free();
        
        if (null === $exception) {
            $exception = new ClosedException('The datagram was closed.');
        }
        
        if (null !== $this->deferred) {
            $this->deferred->reject($exception);
            $this->deferred = null;
        }
        
        while (!$this->writeQueue->isEmpty()) {
            list( , , , $deferred) = $this->writeQueue->shift();
            $deferred->reject($exception);
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
     *
     * @resolve [string, int, string] Array containing the senders remote address, remote port, and data received.
     *
     * @reject  BusyException If a read was already pending on the datagram.
     * @reject  UnreadableException If the datagram is no longer readable.
     * @reject  ClosedException If the datagram has been closed.
     * @reject  TimeoutException If receiving times out.
     * @reject  FailureException If receiving fails.
     */
    public function receive($length = null, $timeout = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on datagram.'));
        }
        
        if (!$this->isOpen()) {
            return Promise::reject(new UnavailableException('The datagram is no longer readable.'));
        }
        
        if (null === $length) {
            $this->length = self::CHUNK_SIZE;
        } else {
            $this->length = (int) $length;
            if (0 > $this->length) {
                $this->length = 0;
            }
        }
        
        $this->poll->listen($timeout);
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * @param   int|float|null $timeout
     *
     * @return  PromiseInterface
     *
     * @resolve int Always resolves with 0.
     *
     * @reject  BusyException If the datagram was already waiting on a read.
     * @reject  UnavailableException If the datagram is no longer readable.
     * @reject  ClosedException If the datagram closes.
     * @reject  TimeoutException If polling times out.
     * @reject  FailureExcpetion If polling fails.
     */
    public function poll($timeout = null)
    {
        return $this->receive(0, $timeout);
    }
    
    /**
     * @param   int|string $address IP address of receiver.
     * @param   int $port Port of receiver.
     * @param   string|null $data Data to send.
     * @param   int|float|null $timeout
     *
     * @return  PromiseInterface
     *
     * @resolve int Number of bytes written.
     *
     * @reject  UnavailableException If the datagram is no longer writable.
     * @reject  ClosedException If the datagram closes.
     * @reject  TimeoutException If sending the data times out.
     * @reject  FailureException If sending data fails.
     */
    public function send($address, $port, $data)
    {
        if (!$this->isOpen()) {
            return Promise::reject(new UnavailableException('The datagram is no longer writable.'));
        }
        
        $data = new Buffer($data);
        
        if (is_int($address)) {
            $address = long2ip($address);
        } elseif (false !== strpos($address, ':')) { // IPv6 address
            $address = '[' . trim($address, '[]') . ']';
        }
        
        $peer = sprintf('%s:%d', $address, $port);
        
        if ($this->writeQueue->isEmpty()) {
            if ($data->isEmpty()) {
                return Promise::resolve(0);
            }
            
            $written = @stream_socket_sendto($this->getResource(), $data->peek(self::CHUNK_SIZE), 0, $peer);
            
            // Having difficulty finding a test to cover this scenario, but the check seems appropriate.
            // @codeCoverageIgnoreStart
            if (false === $written || -1 === $written) {
                $message = 'Failed to write to datagram.';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= " Errno: {$error['type']}; {$error['message']}";
                }
                $exception = new FailureException($message);
                $this->close($exception);
                return Promise::reject($exception);
            } // @codeCoverageIgnoreEnd
            
            if ($data->getLength() <= $written) {
                return Promise::resolve($written);
            }
            
            $data->remove($written);
        } else {
            $written = 0;
        }
        
        $deferred = new Deferred();
        $this->writeQueue->push([$data, $written, $peer, $deferred]);
        
        if (!$this->await->isPending()) {
            $this->await->listen();
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * @return  PromiseInterface
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
     * @return  PollInterface
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
            
            $data = @stream_socket_recvfrom($resource, $this->length, 0, $peer);
            
            // Having difficulty finding a test to cover this scenario, but the check seems appropriate.
            // @codeCoverageIgnoreStart
            if (false === $data) { // Reading failed, so close datagram.
                $message = 'Failed to read from datagram.';
                $error = error_get_last();
                if (null !== $error) {
                    $message .= " Errno: {$error['type']}; {$error['message']}";
                }
                $this->close(new FailureException($message));
                return;
            } // @codeCoverageIgnoreEnd
            
            $colon = strrpos($peer, ':');
            
            $address = substr($peer, 0, $colon);
            $port = (int) substr($peer, $colon + 1);
            
            if (false !== strpos($address, ':')) { // IPv6 address
                $address = '[' . trim($address, '[]') . ']';
            }
            
            $this->deferred->resolve([$address, $port, $data]);
            $this->deferred = null;
        });
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     *
     * @return  AwaitInterface
     */
    protected function createAwait($socket)
    {
        return Loop::await($socket, function ($resource) use (&$onWrite) {
            list($data, $previous, $peer, $deferred) = $this->writeQueue->shift();
            
            if (!$data->isEmpty()) {
                $written = @stream_socket_sendto($resource, $data->peek(self::CHUNK_SIZE), 0, $peer);
                
                // Having difficulty finding a test to cover this scenario, but the check seems appropriate.
                // @codeCoverageIgnoreStart
                if (false === $written || 0 >= $written) {
                    $message = 'Failed to write to datagram.';
                    $error = error_get_last();
                    if (null !== $error) {
                        $message .= " Errno: {$error['type']}; {$error['message']}";
                    }
                    $exception = new FailureException($message);
                    $deferred->reject($exception);
                    $this->close($exception);
                    return;
                } // @codeCoverageIgnoreEnd
                
                if ($data->getLength() <= $written) {
                    $deferred->resolve($written + $previous);
                } else {
                    $data->remove($written);
                    $written += $previous;
                    $this->writeQueue->unshift([$data, $written, $peer, $deferred]);
                }
            } else {
                $deferred->resolve($previous);
            }
            
            if (!$this->writeQueue->isEmpty()) {
                $this->await->listen();
            }
        });
    }
}
