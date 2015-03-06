<?php
namespace Icicle\Stream;

use Exception;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Stream\Exception\BusyException;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\Structures\Buffer;

/**
 * Serves as buffer that implements the stream interface, allowing consumers to be notified when data is available in
 * the buffer. This class by itself is not particularly useful, but it can be extended to add functionality upon reading 
 * or writing, as well as acting as an example of how stream classes can be implemented.
 */
class Stream implements DuplexStreamInterface
{
    /**
     * @var Buffer
     */
    private $buffer;
    
    /**
     * @var bool
     */
    private $open = true;
    
    /**
     * @var bool
     */
    private $writable = true;
    
    /**
     * @var Deferred|null
     */
    private $deferred;
    
    /**
     * @var int|null
     */
    private $length;
    
    /**
     * @var string|null
     */
    private $byte;
    
    /**
     * Initializes object structures.
     */
    public function __construct()
    {
        $this->buffer = new Buffer();
    }
    
    /**
     * @inheritdoc
     */
    public function isOpen()
    {
        return $this->open;
    }
    
    /**
     * @inheritdoc
     */
    public function close(Exception $exception = null)
    {
        $this->open = false;
        $this->writable = false;
        
        if (null !== $this->deferred) {
            if (null === $exception) {
                $exception = new ClosedException('The stream was closed.');
            }
            
            $this->deferred->reject($exception);
            $this->deferred = null;
        }
    }
    
    /**
     * @inheritdoc
     */
    public function read($length = null)
    {
        return $this->readTo(null, $length);
    }
    
    /**
     * @inheritdoc
     */
    public function readTo($byte, $length = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }
        
        if (null === $byte) {
            $this->byte = null;
        } else {
            $this->byte = is_int($byte) ? pack('C', $byte) : (string) $byte;
            $this->byte = strlen($this->byte) ? $this->byte[0] : null;
        }
        
        $this->length = $length;
        
        if (null !== $this->length) {
            $this->length = (int) $this->length;
            if (0 > $this->length) {
                $this->length = 0;
            }
        }
        
        if (!$this->buffer->isEmpty()) {
            if (null !== $this->byte && false !== ($position = $this->buffer->search($this->byte))) {
                if (null === $this->length || $position < $this->length) {
                    return Promise::resolve($this->buffer->remove($position + 1));
                }
                
                return Promise::resolve($this->buffer->remove($this->length));
            }
            
            if (null === $this->length) {
                return Promise::resolve($this->buffer->drain());
            }
            
            return Promise::resolve($this->buffer->remove($this->length));
        }
        
        $this->deferred = new Deferred(function () {
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * @inheritdoc
     */
    public function isReadable()
    {
        return $this->isOpen();
    }
    
    /**
     * @inheritdoc
     */
    public function poll()
    {
        return $this->read(0);
    }
    
    /**
     * @inheritdoc
     */
    public function write($data)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        return $this->send($data);
    }
    
    /**
     * @param   string $data
     *
     * @return  PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     */
    protected function send($data)
    {
        $data = (string) $data; // Single cast in case an object is passed.
        $this->buffer->push($data);
        
        if (null !== $this->deferred && !$this->buffer->isEmpty()) {
            if (null !== $this->byte && false !== ($position = $this->buffer->search($this->byte))) {
                if (null === $this->length || $position < $this->length) {
                    $this->deferred->resolve($this->buffer->remove($position + 1));
                } else {
                    $this->deferred->resolve($this->buffer->remove($this->length));
                }
            } elseif (null === $this->length) {
                $this->deferred->resolve($this->buffer->drain());
            } else {
                $this->deferred->resolve($this->buffer->remove($this->length));
            }
            
            $this->deferred = null;
        }
        
        return Promise::resolve(strlen($data));
    }
    
    /**
     * @inheritdoc
     */
    public function end($data = null)
    {
        $promise = $this->write($data);
        
        $this->writable = false;
        
        $promise->after(function () {
            $this->close();
        });
        
        return $promise;
    }
    
    /**
     * @inheritdoc
     */
    public function await()
    {
        return $this->write(null);
    }
    
    /**
     * @inheritdoc
     */
    public function isWritable()
    {
        return $this->writable;
    }
    
    /**
     * @inheritdoc
     */
    public function pipe(WritableStreamInterface $stream, $endOnClose = true, $length = null)
    {
        return $this->pipeTo($stream, null, $endOnClose, $length);
    }
    
    /**
     * @inheritdoc
     */
    public function pipeTo(WritableStreamInterface $stream, $byte, $endOnClose = true, $length = null)
    {
        if (!$stream->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is not writable.'));
        }
        
        if (null !== $length) {
            $length = (int) $length;
            if (0 > $length) {
                return Promise::resolve(0);
            }
        }
        
        if ($byte !== null) {
            $byte = is_int($byte) ? pack('C', $byte) : (string) $byte;
            $byte = strlen($byte) ? $byte[0] : null;
        }
        
        $result = new Promise(
            function ($resolve, $reject) use (&$promise, $stream, $byte, $length) {
                $handler = function ($data) use (&$handler, &$promise, &$length, $stream, $byte, $resolve, $reject) {
                    static $bytes = 0;
                    $count = strlen($data);
                    $bytes += $count;
                    
                    $promise = $stream->write($data);
                    
                    if (null !== $byte && $data[$count - 1] === $byte) {
                        $resolve($bytes);
                        return;
                    }
                    
                    if (null !== $length && 0 >= $length -= $count) {
                        $resolve($bytes);
                        return;
                    }
                    
                    $promise = $promise->then(
                        function () use ($byte, $length) {
                            return $this->readTo($byte, $length);
                        },
                        function (Exception $exception) use ($bytes, $resolve) {
                            $resolve($bytes);
                            throw $exception;
                        }
                    );
                    
                    $promise->done($handler, $reject);
                };
                
                $promise = $this->readTo($byte, $length);
                $promise->done($handler, $reject);
            },
            function (Exception $exception) use (&$promise) {
                $promise->cancel($exception);
            }
        );
        
        if ($endOnClose) {
            $result->done(null, function () use ($stream) {
                if (!$this->isOpen()) {
                    $stream->end();
                }
            });
        }
        
        return $result;
    }
}
