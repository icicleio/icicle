<?php
namespace Icicle\Stream;

use Exception;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Stream\Exception\BusyException;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Structures\Buffer;

/**
 * Serves as buffer that implements the stream interface, allowing consumers to be notified when data is available in the
 * buffer. This class by itself is not particularly useful, but it can be extended to add functionality upon reading or 
 * writing, as well as acting as an example of how stream classes can be implemented.
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
    private $pattern;
    
    /**
     * Initializes object structures.
     */
    public function __construct()
    {
        $this->buffer = new Buffer();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isOpen()
    {
        return $this->open;
    }
    
    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function read($length = null)
    {
        return $this->readTo(null, $length);
    }
    
    /**
     * {@inheritdoc}
     */
    public function readTo($pattern, $length = null)
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
        
        $this->length = $length;
        
        if (null !== $this->length) {
            $this->length = (int) $this->length;
            if (0 > $this->length) {
                $this->length = 0;
            }
        }
        
        if (!$this->buffer->isEmpty()) {
            if (null !== $this->pattern && ($position = $this->buffer->search($this->pattern))) {
                if (null === $this->length || $position < $this->length) {
                    return Promise::resolve($this->buffer->remove($position + strlen($this->pattern)));
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
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return $this->isOpen();
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
        $data = (string) $data;
        
        if (!empty($data)) {
            $this->buffer->push($data);
            
            if (null !== $this->deferred) {
                if (null !== $this->pattern && ($position = $this->buffer->search($this->pattern))) {
                    if (null === $this->length || $position < $this->length) {
                        $this->deferred->resolve($this->buffer->remove($position + strlen($this->pattern)));
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
        }
        
        return Promise::resolve(strlen($data));
    }
    
    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function await()
    {
        return $this->write(null);
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
    public function pipe(WritableStreamInterface $stream, $endOnClose = true)
    {
        if (!$stream->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is not writable.'));
        }
        
        $result = new Promise(
            function ($resolve, $reject) use (&$promise, $stream) {
                $handler = function ($data) use (&$handler, &$promise, $resolve, $reject, $stream) {
                    static $bytes = 0;
                    if (!empty($data)) {
                        $bytes += strlen($data);
                        $promise = $stream->write($data);
                        $promise->done(null, function () use (&$bytes, $resolve) {
                            $resolve($bytes);
                        });
                    }
                    $promise = $promise->then(function () {
                        return $this->read();
                    });
                    $promise->done($handler, $reject);
                };
                
                $promise = $this->read();
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
