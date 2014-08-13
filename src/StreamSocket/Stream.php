<?php
namespace Icicle\StreamSocket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\DeferredPromise;
use Icicle\Promise\Promise;
use Icicle\Socket\DuplexSocketInterface;
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

class Stream extends Socket implements DuplexSocketInterface, DuplexStreamInterface
{
    const CHUNK_SIZE = 8192; // 8kB
    
    /**
     * @var     float
     */
    private $timeout;
    
    /**
     * @var     DeferredPromise|null
     */
    private $deferred;
    
    /**
     * @var     int
     */
    private $length = 0;
    
    /**
     * @var     WritableStreamInterface|null
     */
    private $destination;
    
    /**
     * Queue of data to write and promises to resolve when that data is written (or fails to write).
     * Data is stored as an array: [Buffer, int, DeferredPromise].
     *
     * @var     SplQueue
     */
    private $writeQueue;
    
    /**
     * @var     bool
     */
    private $writable = true;
    
    /**
     * @param   resource $socket
     * @param   int $timeout
     */
    public function __construct($socket, $timeout = self::DEFAULT_TIMEOUT)
    {
        parent::__construct($socket);
        
        $this->timeout = (float) $timeout;
        
        if (self::NO_TIMEOUT !== $this->timeout && self::MIN_TIMEOUT > $this->timeout) {
            $this->timeout = self::MIN_TIMEOUT;
        }
        
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
            
            if (null === $exception) {
                $exception = new ClosedException('The socket was closed.');
            }
            
            if (null !== $this->deferred) {
                $this->deferred->reject($exception);
                $this->deferred = null;
            }
            
            while (!$this->writeQueue->isEmpty()) {
                list( , , $deferred) = $this->writeQueue->shift();
                $deferred->reject($exception);
            }
            
            Loop::getInstance()->removeSocket($this);
        }
        
        parent::close();
    }
    
    /**
     * {@inheritdoc}
     */
    public function read($length = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        if (null !== $length) {
            $length = (int) $length;
            
            if (0 > $length) {
                $length = 0;
            }
        }
        
        $this->length = $length;
        
        $this->deferred = new DeferredPromise(function () {
            Loop::getInstance()->unscheduleReadableSocket($this);
            $this->deferred = null;
        });
        
        Loop::getInstance()->scheduleReadableSocket($this);
        
        return $this->deferred->getPromise();
    }
    
    /**
     * {@inheritdoc}
     */
    public function poll()
    {
        return $this->read(0);
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
    public function write($data = null)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        $data = new Buffer($data);
        
        if (!$this->writeQueue->isEmpty()) {
            $deferred = new DeferredPromise();
            $this->writeQueue->push([$data, 0, $deferred]);
            return $deferred->getPromise();
        }
        
        $written = @fwrite($this->getResource(), $data, self::CHUNK_SIZE);
        
        if (false === $written) {
            $exception = new FailureException('Writing to socket failed.');
            $this->close($exception);
            return Promise::reject($exception);
        }
        
        if ($data->getLength() > $written) {
            $data->remove($written);
            $deferred = new DeferredPromise();
            $this->writeQueue->push([$data, $written, $deferred]);
            Loop::getInstance()->scheduleWritableSocket($this);
            return $deferred->getPromise();
        }
        
        return Promise::resolve($written);
    }
    
    /**
     * {@inheritdoc}
     */
    public function end($data = null)
    {
        $promise = $this->write($data);
        
        $this->writable = false;
        
        return $promise->cleanup(function () {
            $this->close(new ClosedException('The stream was ended.'));
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function await()
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        $deferred = new DeferredPromise();
        
        $this->writeQueue->push([new Buffer(), 0, $deferred]);
        
        Loop::getInstance()->scheduleWritableSocket($this);
        
        return $deferred->getPromise();
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
    
    /**
     * {@inheritdoc}
     */
    public function onTimeout()
    {
        $this->deferred->reject(new TimeoutException('The connection timed out.'));
        $this->deferred = null;
    }
    
    /**
     * {@inheritdoc}
     */
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
    
    /**
     * {@inheritdoc}
     */
    public function getTimeout()
    {
        return $this->timeout;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (float) $timeout;
        
        if (self::NO_TIMEOUT !== $this->timeout && self::MIN_TIMEOUT > $this->timeout) {
            $this->timeout = self::MIN_TIMEOUT;
        }
        
        $loop = Loop::getInstance();
        
        if ($loop->isReadableSocketScheduled($this)) {
            $loop->unscheduleReadableSocket($this);
            $loop->scheduleReadableSocket($this);
        }
    }
    
    public function pipe(WritableStreamInterface $stream, callable $onTimeout = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        $this->length = null;
        $this->destination = $stream;
        
        Loop::getInstance()->scheduleReadableSocket($this);
        
        $this->deferred = new DeferredPromise(function () {
            Loop::getInstance()->unscheduleReadableSocket($this);
            $this->deferred = null;
            $this->destination = null;
        });
        
        return $this->deferred->getPromise();
    }
}
