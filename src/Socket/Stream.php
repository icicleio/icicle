<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Stream\DuplexStreamInterface;
use Icicle\Stream\Exception\BusyException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\WritableStreamInterface;
use Icicle\Structures\Buffer;
use SplQueue;

class Stream extends Socket implements DuplexStreamInterface
{
    const CHUNK_SIZE = 8192; // 8kB
    
    /**
     * @var Deferred|null
     */
    private $deferred;
    
    /**
     * Queue of data to write and promises to resolve when that data is written (or fails to write).
     * Data is stored as an array: [Buffer, int, int|float|null, Deferred].
     *
     * @var SplQueue
     */
    private $writeQueue;
    
    /**
     * @var bool
     */
    private $writable = true;
    
    /**
     * @var PollInterface|null
     */
    private $poll;
    
    /**
     * @var AwaitInterface|null
     */
    private $await;
    
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
     * {@inheritdoc}
     */
    public function read($length = null, $timeout = null)
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
                $this->deferred->reject(new TimeoutException('The connection timed out.'));
                $this->deferred = null;
                return;
            }
            
            if (@feof($resource)) { // Connection closed, so close stream.
                $this->close(new ClosedException('Connection reset by peer or reached EOF.'));
                return;
            }
            
            if (0 === $length) {
                $data = '';
            } else {
                $data = @fread($resource, $length);
                
                if (false === $data) { // Reading failed, so close stream.
                    $this->close(new FailureException('Reading from the socket failed.'));
                    return;
                }
            }
            
            $this->deferred->resolve($data);
            $this->deferred = null;
        };
        
        if (null === $this->poll) {
            $this->poll = Loop::poll($this->getResource(), $onRead);
        } else {
            $this->poll->setCallback($onRead);
        }
        
        $this->poll->listen($timeout);
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * {@inheritdoc}
     */
    public function poll($timeout = null)
    {
        return $this->read(0, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return $this->isOpen();
    }
    
    /**
     * {@inheritdoc}
     */
    public function write($data = null, $timeout = null)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        $data = new Buffer($data);
        
        if ($this->writeQueue->isEmpty() && !$data->isEmpty()) {
            $written = @fwrite($this->getResource(), $data, self::CHUNK_SIZE);
            
            if (false === $written) {
                $exception = new FailureException('Failed to write to stream.');
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
        $this->writeQueue->push([$data, $written, $timeout, $deferred]);
        
        if (null === $this->await) {
            $onWrite = function ($resource, $expired) {
                if ($expired) {
                    $this->close(new TimeoutException('Writing to the socket timed out.'));
                    return;
                }
                
                list($data, $previous, $timeout, $deferred) = $this->writeQueue->shift();
                
                $written = @fwrite($resource, $data, self::CHUNK_SIZE);
                
                if (false === $written || (0 === $written && !$data->isEmpty())) {
                    $exception = new FailureException('Failed to write to stream.');
                    $deferred->reject($exception);
                    $this->close($exception);
                    return;
                }
                
                $data->remove($written);
                $written += $previous;
                
                if ($data->isEmpty()) {
                    $deferred->resolve($written);
                } else {
                    $this->writeQueue->unshift([$data, $written, $timeout, $deferred]);
                }
                
                if (!$this->writeQueue->isEmpty()) {
                    list( , , $timeout) = $this->writeQueue->top();
                    $this->await->listen($timeout);
                }
            };
            
            $this->await = Loop::await($this->getResource(), $onWrite);
        }
        
        if (!$this->await->isPending()) {
            $this->await->listen($timeout);
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * {@inheritdoc}
     */
    public function end($data = null, $timeout = null)
    {
        $promise = $this->write($data, $timeout);
        
        $this->writable = false;
        
        return $promise->cleanup(function () {
            $this->close(new ClosedException('The stream was ended.'));
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function await($timeout = null)
    {
        return $this->write(null, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return $this->writable;
    }
    
    /**
     * {@inheritdoc}
     */
/*
    public function onRead()
    {
        $socket = $this->getResource();
        
        if (@feof($socket)) {
            $exception = new ClosedException('Connection reset by peer.');
            $this->deferred->reject($exception);
            $this->deferred = null;
            $this->close($exception);
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
            $data = @fread($socket, $length);
            
            if (false === $data) { // Reading failed, so close connection.
                $exception = new FailureException('Reading from the socket failed.');
                $this->deferred->reject($exception);
                $this->deferred = null;
                $this->close($exception);
                return;
            }
        }
        
        if (null !== $this->destination) {
            $this->destination->write($data)->done(
                function () {
                    Loop::getInstance()->scheduleReadableSocket($this);
                },
                function (Exception $exception) {
                    $this->deferred->reject($exception);
                    $this->deferred = null;
                    $this->destination = null;
                }
            );
            return;
        }
        
        $this->deferred->resolve($data);
        $this->deferred = null;
    }
*/
    
    /**
     * {@inheritdoc}
     */
/*
    public function onTimeout()
    {
        $this->deferred->reject(new TimeoutException('The connection timed out.'));
        $this->deferred = null;
    }
*/
    
    /**
     * {@inheritdoc}
     */
/*
    public function onWrite()
    {
        list($data, $previous, $deferred) = $this->writeQueue->shift();
        
        $written = @fwrite($this->getResource(), $data, self::CHUNK_SIZE);
        
        if (false === $written || (0 === $written && !$data->isEmpty())) {
            $this->writable = false;
            $exception = new FailureException('Could not write to socket.');
            $deferred->reject($exception);
            while (!$this->writeQueue->isEmpty()) {
                list( , , $deferred) = $this->writeQueue->shift();
                $deferred->reject($exception);
            }
            return;
        }
        
        $data->remove($written);
        
        $written += $previous;
        
        if ($data->isEmpty()) {
            $deferred->resolve($written);
        } else {
            $this->writeQueue->unshift([$data, $written, $deferred]);
        }
        
        if (!$this->writeQueue->isEmpty()) {
            Loop::getInstance()->scheduleWritableSocket($this);
        }
    }
*/
    
    public function pipe(WritableStreamInterface $stream, $endOnClose = true)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        $onRead = function ($resource) use (&$onRead, $destination, $endOnClose) {
            if (@feof($resource)) { // Socket closed, so close stream.
                $this->close(new ClosedException('Connection reset by peer or reached EOF.'));
                return;
            }
            
            $data = @fread($resource, self::CHUNK_SIZE);
            
            if (false === $data) { // Reading failed, so close stream.
                if ($endOnClose) { $destination->end(); }
                $this->close(new FailureException('Reading from the socket failed.'));
                return;
            }
            
            $destination->write($data)->done(
                function () use ($resource, $onRead) {
                    $this->poll->listen();
                },
                function (Exception $exception) {
                    $this->deferred->reject($exception);
                    $this->deferred = null;
                }
            );
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
}
