<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\EofException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Stream\Exception\BusyException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\WritableStreamInterface;

trait ReadableStreamTrait
{
    /**
     * @var Deferred|null
     */
    private $deferred;
    
    /**
     * @var PollInterface
     */
    private $poll;
    
    /**
     * @var int
     */
    private $length = 0;
    
    /**
     * @var string|null
     */
    private $pattern;
    
    /**
     * @return  resource Socket resource.
     */
    abstract protected function getResource();
    
    /**
     * Determines if the stream is still open.
     *
     * @return  bool
     */
    abstract public function isOpen();
    
    /**
     * Closes the socket if it is still open.
     *
     * @param   Exception|null $exception
     */
    abstract public function close(Exception $exception = null);
    
    /**
     * @param   resource $socket Socket resource.
     */
    private function init($socket)
    {
        stream_set_read_buffer($socket, 0);
        stream_set_chunk_size($socket, self::CHUNK_SIZE);
        
        $this->poll = Loop::poll($socket, function ($resource, $expired) {
            if ($expired) {
                $this->deferred->reject(new TimeoutException('The connection timed out.'));
                $this->deferred = null;
                return;
            }
            
            $data = null;
            
            if (0 !== $this->length) {
                if (null !== $this->pattern) {
                    $length = strlen($this->pattern);
                    $offset = -$length;
                    
                    $i = 0;
                    while ($i < $this->length) {
                        if (false === ($byte = fgetc($resource))) {
                            break;
                        }
                        $data .= $byte;
                        if (++$i >= $length && 0 === substr_compare($data, $this->pattern, $offset, $length)) {
                            break;
                        }
                    }
                } elseif (!feof($resource)) {
                    $data = fread($resource, $this->length);
                }
            }
            
            if (null === $data && feof($resource)) { // Close only if no data was read and at EOF.
                $this->close(new EofException('Connection reset by peer or reached EOF.'));
                return;
            }
            
            $this->deferred->resolve($data);
            $this->deferred = null;
        });
    }
    
    /**
     * Frees all resources used by the writable stream.
     *
     * @param   Exception $exception
     */
    private function free(Exception $exception)
    {
        $this->poll->free();
        
        if (null !== $this->deferred) {
            $this->deferred->reject($exception);
            $this->deferred = null;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function read($length = null, $timeout = null)
    {
        return $this->readTo(null, $length, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function readTo($pattern, $length = null, $timeout = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        if (null === $pattern) {
            $this->pattern = null;
        } else {
            $this->pattern = (string) $pattern;
            if (!strlen($this->pattern)) {
                $this->pattern = null;
            }
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
     * {@inheritdoc}
     */
    public function poll($timeout = null)
    {
        return $this->readTo(null, 0, $timeout);
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
    public function pipe(WritableStreamInterface $stream, $endOnClose = true, $timeout = null)
    {
        if (!$stream->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is not writable.'));
        }
        
        $result = new Promise(
            function ($resolve, $reject) use (&$promise, $stream, $timeout) {
                $handler = function ($data) use (&$handler, &$promise, $resolve, $reject, $stream, $timeout) {
                    static $bytes = 0;
                    
                    if (!empty($data)) {
                        $bytes += strlen($data);
                        $promise = $stream->write($data, $timeout);
                        $promise->done(null, function () use (&$bytes, $resolve) {
                            $resolve($bytes);
                        });
                    }
                    
                    $promise = $promise->then(function () use ($timeout) {
                        return $this->read(null, $timeout);
                    });
                    
                    $promise->done($handler, $reject);
                };
                
                $promise = $this->read(null, $timeout);
                $promise->done($handler, $reject);
            },
            function (Exception $exception) use (&$promise) {
                $promise->cancel($exception);
            }
        );
        
        if ($endOnClose) {
            $result->done(null, function () use ($stream) {
                $stream->end();
            });
        }
        
        return $result;
    }
}
