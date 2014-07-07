<?php
namespace Icicle\PromiseSocket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\DeferredPromise;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\BusyException;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\DuplexSocketInterface;
use Icicle\Stream\DuplexStreamInterface;
use Icicle\Stream\WritableStreamInterface;
use Icicle\Structures\Buffer;
use SplQueue;

class Stream extends Socket implements DuplexSocketInterface, DuplexStreamInterface
{
    const CHUNK_SIZE = 8192; // 8kb
    
    const MAX_BUFFER_LENGTH = 65536; // 64kb
    
    /**
     * Data read from socket is buffered here until read() is called.
     *
     * @var     Buffer
     */
    private $readBuffer;
    
    /**
     * @var     int
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
     * Data is stored as a pair array: [Buffer, DeferredPromise].
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
        
        $this->timeout = (int) $timeout;
        
        if (0 >= $this->timeout) {
            $this->timeout = self::NO_TIMEOUT;
        }
        
        stream_set_read_buffer($socket, 0);
        stream_set_write_buffer($socket, 0);
        stream_set_chunk_size($socket, self::CHUNK_SIZE);
        stream_set_blocking($socket, 0);
        
        $this->readBuffer = new Buffer();
        $this->writeQueue = new SplQueue();
        
        Loop::getInstance()->addReadableSocket($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function close()
    {
        if ($this->isOpen()) {
            $exception = new ClosedException('The stream was closed.');
            
            if (null !== $this->deferred) {
                $this->deferred->reject($exception);
                $this->deferred = null;
            }
            
            $this->writable = false;
            
            while (!$this->writeQueue->isEmpty()) {
                list($data, $deferred) = $this->writeQueue->shift();
                $deferred->reject($exception);
            }
            
            Loop::getInstance()->removeSocket($this);
            
            parent::close();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function read($length = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (null !== $length) {
            $length = (int) $length;
        }
        
        if (0 === $length) {
            return Promise::resolve('');
        }
        
        if ($this->readBuffer->isEmpty() || (null !== $length && $this->readBuffer->getLength() < $length)) {
            if (!$this->isOpen()) {
                return Promise::reject(new ClosedException('The stream has closed.'));
            }
            
            $this->resume();
            
            $this->length = $length;
            $this->deferred = new DeferredPromise(function () { $this->deferred = null; });
            return $this->deferred->getPromise();
        }
        
        if (null === $length) {
            return Promise::resolve($this->readBuffer->drain());
        }
        
        return Promise::resolve($this->readBuffer->remove($length));
    }
    
    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return !$this->readBuffer->isEmpty() || $this->isOpen();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return $this->readBuffer->isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        return $this->readBuffer->getLength();
    }
    
    /**
     * {@inheritdoc}
     */
    public function unshift($data)
    {
        $this->readBuffer->unshift($data);
    }
    
    /**
     * {@inheritdoc}
     */
    public function write($data, callable $callback = null)
    {
        if (!$this->writable) {
            return Promise::reject(new ClosedException('The stream is no longer writable.'));
        }
        
        $data = new Buffer($data);
        
        if (!$this->writeQueue->isEmpty()) {
            $deferred = new DeferredPromise();
            $this->writeQueue->push([$data, $deferred]);
            return $deferred->getPromise();
        }
        
        if (0 === $data->getLength()) {
            return Promise::resolve(0);
        }
        
        $written = $this->send($data);
        
        if (false === $written) {
            $this->close();
            return Promise::reject(new FailureException('Writing to socket failed.'));
        }
        
        if ($data->getLength() !== $written) {
            $data->remove($written);
            $deferred = new DeferredPromise();
            $this->writeQueue->push([$data, $deferred]);
            Loop::getInstance()->scheduleWritableSocket($this);
            return $deferred->getPromise();
        }
        
        return Promise::resolve($written);
    }
    
    /**
     * {@inheritdoc}
     */
    public function end($data = null, callable $callback = null)
    {
        $promise = $this->write($data);
        
        $this->writable = false;
        
        return $promise->cleanup([$this, 'close']);
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
        
        if (feof($socket)) {
            if (null !== $this->deferred) {
                $this->deferred->reject(new ClosedException('Connection reset by peer.'));
                $this->deferred = null;
            }
            $this->close();
            return;
        }
        
        $data = @fread($socket, self::CHUNK_SIZE);
        
        if (false === $data) { // Reading failed, so close connection.
            if (null !== $this->deferred) {
                $this->deferred->reject(new FailureException('Reading from the socket failed.'));
                $this->deferred = null;
            }
            $this->close();
            return;
        }
        
        if (empty($data)) {
            return; // Ignore if no data was read.
        }
        
        $this->readBuffer->push($data);
        
        if (null !== $this->destination)
        {
            $promise = $this->destination->write($this->readBuffer->drain());
            
            if ($promise->isPending() || $promise->isRejected()) {
                $this->pause();
                $promise->done(
                    function () {
                        $this->resume();
                    },
                    function (Exception $exception) {
                        $this->destination = null;
                        $this->deferred->reject($exception);
                        $this->deferred = null;
                    }
                );
            }
        } elseif (null !== $this->deferred) {
            if (null === $this->length) {
                $this->deferred->resolve($this->readBuffer->drain());
                $this->deferred = null;
            } elseif ($this->readBuffer->getLength() >= $this->length) {
                $this->deferred->resolve($this->readBuffer->remove($this->length));
                $this->deferred = null;
            }
        } elseif ($this->readBuffer->getLength() > self::MAX_BUFFER_LENGTH) {
            $this->pause();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function onTimeout()
    {
        if (null !== $this->destination) {
            return;
        }
        
        if (null !== $this->deferred) {
            $this->deferred->reject(new TimeoutException('The connection timed out.'));
            $this->deferred = null;
        } else {
            $this->close();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function onWrite()
    {
        if ($this->writeQueue->isEmpty()) {
            return;
        }
        
        list($data, $deferred) = $this->writeQueue->shift();
        
        if ($data->isEmpty()) {
            $deferred->resolve(0);
        } else {
            $written = $this->send($data);
            
            if (!$written) {
                $exception = new FailureException('Could not write to socket.');
                $deferred->reject($exception);
                $this->writable = false;
                while (!$this->writeQueue->isEmpty()) {
                    list($data, $deferred) = $this->writeQueue->shift();
                    $deferred->reject($exception);
                }
                return;
            }
            
            $data->remove($written);
            
            if ($data->isEmpty()) {
                $deferred->resolve($written);
            } else {
                $this->writeQueue->unshift([$data, $deferred]);
            }
        }
        
        if (!$this->writeQueue->isEmpty()) {
            Loop::getInstance()->scheduleWritableSocket($this);
        }
    }
    
    /**
     * Writes as much of the buffer as possible to the socket. Does not block. Returns the number of bytes read
     * or false if an error occurs. The buffer is not modified.
     *
     * @param   Buffer $buffer
     *
     * @return  int|false Number of bytes written or false if an error occurs.
     */
    protected function send(Buffer $buffer)
    {
        return @fwrite($this->getResource(), $buffer->peek(self::CHUNK_SIZE), self::CHUNK_SIZE);
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
        $this->timeout = (int) $timeout;
        
        if (0 > $this->timeout) {
            $this->timeout = self::NO_TIMEOUT;
        }
        
        Loop::getInstance()->pauseReadableSocket($this);
        Loop::getInstance()->resumeReadableSocket($this);
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
        Loop::getInstance()->pauseReadableSocket($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function resume()
    {
        Loop::getInstance()->resumeReadableSocket($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pipe(WritableStreamInterface $destination, $end = true)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('The stream is already busy.'));
        }
        
        if (!$destination->isWritable()) {
            return Promise::reject(new ClosedException('The destination is not writable.'));
        }
        
        $this->destination = $destination;
        $this->deferred = new DeferredPromise();
        $promise = $this->deferred->getPromise();
        
        $this->resume();
        
        if ($end) {
            $promise = $promise->capture(function (Exception $exception) use ($destination) {
                $destination->end();
                throw $exception;
            });
        }
        
        if (!$this->readBuffer->isEmpty()) {
            $this->destination
                ->write($this->readBuffer->drain())
                ->otherwise(function (Exception $exception) {
                    $this->destination = null;
                    $this->deferred->reject($exception);
                    $this->deferred = null;
                });
        }
        
        return $promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function unpipe(WritableStreamInterface $destination = null)
    {
        if (null !== $this->destination && null !== $this->deferred) {
            $destination = $this->destination;
            $this->destination = null;
            $this->deferred->resolve();
            $this->deferred = null;
            return $destination;
        }
        
        return null;
    }
}
