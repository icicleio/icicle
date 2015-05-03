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
    use ParserTrait;
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
     * @var int|null
     */
    private $length;
    
    /**
     * @var string|null
     */
    private $byte;

    /**
     * @var int|null
     */
    private $hwm;

    /**
     * @var \SplQueue|null
     */
    private $deferredQueue;
    
    /**
     * @param   int|null $hwm High water mark. If the internal buffer has more than $hwm bytes, writes to the stream
     *          will return pending promises until the data is consumed.
     */
    public function __construct($hwm = null)
    {
        $this->buffer = new Buffer();
        $this->hwm = $this->parseLength($hwm);

        if (null !== $this->hwm) {
            $this->deferredQueue = new \SplQueue();
        }
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
    public function close()
    {
        if ($this->isOpen()) {
            $this->free(new ClosedException('The stream was closed.'));
        }
    }

    /**
     * Closes the stream and rejects any pending promises.
     *
     * @param   \Exception $exception
     */
    protected function free(Exception $exception)
    {
        $this->open = false;
        $this->writable = false;

        if (null !== $this->deferred) {
            $this->deferred->reject($exception);
            $this->deferred = null;
        }

        if (null !== $this->hwm) {
            while (!$this->deferredQueue->isEmpty()) {
                /** @var \Icicle\Promise\Deferred $deferred */
                list( , $deferred) = $this->deferredQueue->shift();
                $deferred->reject($exception);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function read($length = null, $byte = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }

        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }

        $this->length = $this->parseLength($length);
        $this->byte = $this->parseByte($byte);

        if (!$this->buffer->isEmpty()) {
            $data = $this->remove();

            if (null !== $this->hwm && $this->buffer->getLength() <= $this->hwm) {
                while (!$this->deferredQueue->isEmpty()) {
                    /** @var \Icicle\Promise\Deferred $deferred */
                    list($length, $deferred) = $this->deferredQueue->shift();
                    $deferred->resolve($length);
                }
            }

            if (!$this->writable && $this->buffer->isEmpty()) {
                $this->free(new ClosedException('The stream was ended.'));
            }

            return Promise::resolve($data);
        }

        $this->deferred = new Deferred(function () {
            $this->deferred = null;
        });

        return $this->deferred->getPromise();
    }

    /**
     * Returns bytes from the buffer based on the current length or current search byte.
     *
     * @return  string
     */
    private function remove()
    {
        if (null !== $this->byte && false !== ($position = $this->buffer->search($this->byte))) {
            if (null === $this->length || $position < $this->length) {
                return $this->buffer->remove($position + 1);
            }

            return $this->buffer->remove($this->length);
        }

        if (null === $this->length) {
            return $this->buffer->drain();
        }

        return $this->buffer->remove($this->length);
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
    public function write($data)
    {
        return $this->send($data, false);
    }

    /**
     * @inheritdoc
     */
    public function end($data = null)
    {
        return $this->send($data, true);
    }

    /**
     * @param   string $data
     * @param   bool $end
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     */
    protected function send($data, $end = false)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }

        $this->buffer->push($data);

        if (null !== $this->deferred && !$this->buffer->isEmpty()) {
            $this->deferred->resolve($this->remove());
            $this->deferred = null;
        }

        if ($end) {
            $this->writable = false;

            if ($this->buffer->isEmpty()) {
                $this->free(new ClosedException('The stream was ended.'));
            }
        }

        if (null !== $this->hwm && $this->buffer->getLength() > $this->hwm) {
            $deferred = new Deferred();
            $this->deferredQueue->push([strlen($data), $deferred]);
            return $deferred->getPromise();
        }

        return Promise::resolve(strlen($data));
    }

    /**
     * @inheritdoc
     */
    public function isWritable()
    {
        return $this->writable;
    }
}
