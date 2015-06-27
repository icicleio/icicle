<?php
namespace Icicle\Stream;

use Icicle\Stream\Exception\UnwritableException;

trait PipeTrait
{
    /**
     * @see \Icicle\Stream\ReadableStreamInterface::read()
     *
     * @param int|null $length
     * @param string|int|null $byte
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    abstract public function read($length = null, $byte = null);

    /**
     * @see \Icicle\Stream\ReadableStreamInterface::isReadable()
     *
     * @return bool
     */
    abstract public function isReadable();

    /**
     * @see \Icicle\Stream\ParserTrait::parseByte()
     *
     * @param int|null $length
     */
    abstract protected function parseLength($length);

    /**
     * @see \Icicle\Stream\ParserTrait::parseByte()
     *
     * @param string|int|null $byte
     *
     * @return string|null
     */
    abstract protected function parseByte($byte);

    /**
     * @see \Icicle\Stream\ReadableStreamInterface::pipe()
     *
     * @coroutine
     *
     * @param \Icicle\Stream\WritableStreamInterface $stream
     * @param bool $endWhenUnreadable
     * @param int $length
     * @param string|int|null $byte
     * @param float|int $timeout
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     * @reject \Icicle\Promise\Exception\TimeoutException If the operation times out.
     */
    public function pipe(WritableStreamInterface $stream, $endWhenUnreadable = true, $length = 0, $byte = null, $timeout = 0)
    {
        if (!$stream->isWritable()) {
            throw new UnwritableException('The stream is not writable.');
        }

        $length = $this->parseLength($length);
        $byte = $this->parseByte($byte);

        $bytes = 0;

        try {
            do {
                $data = (yield $this->read($length, $byte, $timeout));

                $count = strlen($data);
                $bytes += $count;

                yield $stream->write($data, $timeout);
            } while ($this->isReadable()
                && $stream->isWritable()
                && (null === $byte || $data[$count - 1] !== $byte)
                && (0 === $length || 0 < $length -= $count)
            );
        } finally {
            if ($endWhenUnreadable && !$this->isReadable() && $stream->isWritable()) {
                $stream->end('', $timeout);
            }
        }

        yield $bytes;
    }
}
