<?php
namespace Icicle\Stream;

use Exception;
use Icicle\Promise;
use Icicle\Promise\Deferred;
use Icicle\Stream\Exception\BusyError;
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
    use PipeTrait;

    /**
     * @var \Icicle\Stream\Structures\Buffer
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
     * @var \Icicle\Promise\Deferred|null
     */
    private $deferred;
    
    /**
     * @var int
     */
    private $length;
    
    /**
     * @var string|null
     */
    private $byte;

    /**
     * @var int
     */
    private $hwm;

    /**
     * @var \SplQueue|null
     */
    private $deferredQueue;
    
    /**
     * @param int $hwm High water mark. If the internal buffer has more than $hwm bytes, writes to the stream will
     *     return pending promises until the data is consumed.
     */
    public function __construct($hwm = 0)
    {
        $this->buffer = new Buffer();
        $this->hwm = $this->parseLength($hwm);

        if (0 !== $this->hwm) {
            $this->deferredQueue = new \SplQueue();
        }
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
    public function close()
    {
        $this->free();
    }

    /**
     * Closes the stream and rejects any pending promises.
     *
     * @param \Exception|null $exception
     */
    protected function free(Exception $exception = null)
    {
        $this->open = false;
        $this->writable = false;

        if (null !== $this->deferred) {
            $this->deferred->reject($exception ?: new ClosedException('The stream was unexpectedly closed.'));
            $this->deferred = null;
        }

        if (0 !== $this->hwm) {
            while (!$this->deferredQueue->isEmpty()) {
                /** @var \Icicle\Promise\Deferred $deferred */
                list( , $deferred) = $this->deferredQueue->shift();
                $deferred->reject($exception ?: new ClosedException('The stream was unexpectedly closed.'));
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($length = 0, $byte = null, $timeout = 0)
    {
        if (null !== $this->deferred) {
            return Promise\reject(new BusyError('Already waiting on stream.'));
        }

        if (!$this->isReadable()) {
            return Promise\reject(new UnreadableException('The stream is no longer readable.'));
        }

        $this->length = $this->parseLength($length);
        $this->byte = $this->parseByte($byte);

        if (!$this->buffer->isEmpty()) {
            $data = $this->remove();

            if (0 !== $this->hwm && $this->buffer->getLength() <= $this->hwm) {
                while (!$this->deferredQueue->isEmpty()) {
                    /** @var \Icicle\Promise\Deferred $deferred */
                    list($length, $deferred) = $this->deferredQueue->shift();
                    $deferred->resolve($length);
                }
            }

            if (!$this->writable && $this->buffer->isEmpty()) {
                $this->close();
            }

            return Promise\resolve($data);
        }

        $this->deferred = new Deferred(function () {
            $this->deferred = null;
        });

        $promise = $this->deferred->getPromise();

        if (0 !== $timeout) {
            $promise = $promise->timeout($timeout, 'Reading from the stream timed out.');
        }

        return $promise;
    }

    /**
     * Returns bytes from the buffer based on the current length or current search byte.
     *
     * @return string
     */
    private function remove()
    {
        if (null !== $this->byte && false !== ($position = $this->buffer->search($this->byte))) {
            if (0 === $this->length || $position < $this->length) {
                return $this->buffer->remove($position + 1);
            }

            return $this->buffer->remove($this->length);
        }

        if (0 === $this->length) {
            return $this->buffer->drain();
        }

        return $this->buffer->remove($this->length);
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
    public function write($data, $timeout = 0)
    {
        return $this->send($data, $timeout, false);
    }

    /**
     * {@inheritdoc}
     */
    public function end($data = '', $timeout = 0)
    {
        return $this->send($data, $timeout, true);
    }

    /**
     * @param string $data
     * @param float|int $timeout
     * @param bool $end
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     */
    protected function send($data, $timeout = 0, $end = false)
    {
        if (!$this->isWritable()) {
            return Promise\reject(new UnwritableException('The stream is no longer writable.'));
        }

        $this->buffer->push($data);

        if (null !== $this->deferred && !$this->buffer->isEmpty()) {
            $this->deferred->resolve($this->remove());
            $this->deferred = null;
        }

        if ($end) {
            $this->writable = false;

            if ($this->buffer->isEmpty()) {
                $this->close();
            }
        }

        if (0 !== $this->hwm && $this->buffer->getLength() > $this->hwm) {
            $deferred = new Deferred(function (\Exception $exception) {
                $this->free($exception);
            });
            $this->deferredQueue->push([strlen($data), $deferred]);

            $promise = $deferred->getPromise();
            if (0 !== $timeout) {
                $promise = $promise->timeout($timeout, 'Writing to the stream timed out.');
            }
            return $promise;
        }

        return Promise\resolve(strlen($data));
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return $this->writable;
    }
}
