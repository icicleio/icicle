<?php
namespace Icicle\Stream;

use Icicle\Promise;
use Icicle\Stream\Exception\InvalidArgumentError;
use Icicle\Stream\Exception\OutOfBoundsException;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnseekableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\Structures\Buffer;

/**
 * Acts as a buffered sink with a seekable read/write pointer. All data written to the sink remains in the sink. The
 * read/write pointer may be moved anywhere within the buffered sink using seek(). The current position of the pointer
 * may be determined with tell(). Since all data remains in the sink, the entire length of the sink is available with
 * getLength().
 */
class Sink implements DuplexStreamInterface, SeekableStreamInterface
{
    use PipeTrait;

    /**
     * @var bool
     */
    private $open = true;

    /**
     * @var bool
     */
    private $writable = true;

    /**
     * @var \Icicle\Stream\Structures\Buffer
     */
    private $buffer;

    /**
     * @var \Icicle\Stream\Structures\BufferIterator
     */
    private $iterator;

    /**
     * Initializes empty sink.
     */
    public function __construct()
    {
        $this->buffer = new Buffer();
        $this->iterator = $this->buffer->getIterator();
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
        $this->open = false;
        $this->writable = false;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable()
    {
        return $this->isOpen() && $this->iterator->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function read($length = 0, $byte = null, $timeout = 0)
    {
        if (!$this->isReadable()) {
            return Promise\reject(new UnreadableException('The stream is no longer readable.'));
        }

        $length = $this->parseLength($length);
        $byte = $this->parseByte($byte);

        if (null !== $byte) {
            $data = '';
            $i = 0;
            do {
                $char = $this->iterator->current();
                $this->iterator->next();
                $data .= $char;
            } while ($char !== $byte && (0 === $length || ++$i < $length) && $this->iterator->valid());

            return Promise\resolve($data);
        }

        if (0 === $length) {
            $length = $this->buffer->getLength();
        }

        $position = $this->iterator->key();
        $data = $this->buffer->peek($length, $position);
        $position = $length + $position;

        if ($position > $this->buffer->getLength()) {
            $position = $this->buffer->getLength();
        }

        $this->iterator->seek($position);

        return Promise\resolve($data);
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
     */
    protected function send($data, $timeout = 0, $end = false)
    {
        if (!$this->isWritable()) {
            return Promise\reject(new UnwritableException('The stream is no longer writable.'));
        }

        if ($end) {
            $this->writable = false;
        }

        $length = strlen($data);

        if (!$this->iterator->valid()) {
            $this->buffer->push($data);
        } else {
            $this->iterator->insert($data);
        }

        $this->iterator->seek($this->iterator->key() + $length);

        return Promise\resolve($length);
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET, $timeout = 0)
    {
        if (!$this->isOpen()) {
            return Promise\reject(new UnseekableException('The stream is no longer seekable.'));
        }

        $offset = (int) $offset;

        switch ($whence) {
            case SEEK_SET:
                break;

            case SEEK_CUR:
                $offset += $this->tell();
                break;

            case SEEK_END:
                $offset += $this->getLength();
                break;

            default:
                return Promise\reject(
                    new InvalidArgumentError('Invalid value for whence. Use SEEK_SET, SEEK_CUR, or SEEK_END.')
                );
        }

        if (0 > $offset || $this->buffer->getLength() <= $offset) {
            return Promise\reject(new OutOfBoundsException(sprintf('Invalid offset: %s.', $offset)));
        }

        $this->iterator->seek($offset);

        return Promise\resolve($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function tell()
    {
        return $this->iterator->key();
    }

    /**
     * {@inheritdoc}
     */
    public function getLength()
    {
        return $this->buffer->getLength();
    }
}
