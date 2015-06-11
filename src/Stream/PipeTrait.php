<?php
namespace Icicle\Stream;

use Icicle\Coroutine\Coroutine;
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
     * @param \Icicle\Stream\WritableStreamInterface $stream
     * @param bool $endWhenUnreadable
     * @param int|null $length
     * @param string|int|null $byte
     *
     * @return \Icicle\Coroutine\CoroutineInterface
     */
    public function pipe(WritableStreamInterface $stream, $endWhenUnreadable = true, $length = null, $byte = null)
    {
        return new Coroutine($this->doPipe($stream, $endWhenUnreadable, $length, $byte));
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Stream\WritableStreamInterface $stream
     * @param bool $endWhenUnreadable
     * @param int|null $length
     * @param string|int|null $byte
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     */
    private function doPipe(WritableStreamInterface $stream, $endWhenUnreadable, $length, $byte)
    {
        if (!$stream->isWritable()) {
            throw new UnwritableException('The stream is not writable.');
        }

        $length = $this->parseLength($length);
        $byte = $this->parseByte($byte);

        $bytes = 0;

        if (0 !== $length) {
            try {
                do {
                    $data = (yield $this->read($length, $byte));

                    $count = strlen($data);
                    $bytes += $count;

                    yield $stream->write($data);
                } while ($this->isReadable()
                    && $stream->isWritable()
                    && (null === $byte || $data[$count - 1] !== $byte)
                    && (null === $length || 0 < $length -= $count)
                );
            } finally {
                if ($endWhenUnreadable && !$this->isReadable() && $stream->isWritable()) {
                    $stream->end();
                }
            }
        }

        yield $bytes;
    }
}
