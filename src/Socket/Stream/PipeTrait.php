<?php
namespace Icicle\Socket\Stream;

use Icicle\Coroutine\Coroutine;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\WritableStreamInterface;

trait PipeTrait
{
    /**
     * @see \Icicle\Socket\Stream\ReadableSocketInterface::read()
     *
     * @param int|null $length
     * @param string|int|null $byte
     * @param float|int|null $timeout
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    abstract public function read($length = null, $byte = null, $timeout = null);

    /**
     * @see \Icicle\Socket\Stream\ReadableSocketInterface::isReadable()
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
     * @see \Icicle\Socket\Stream\ReadableSocketInterface::pipe()
     *
     * @param \Icicle\Stream\WritableStreamInterface $stream
     * @param bool $endWhenUnreadable
     * @param int|null $length
     * @param string|int|null $byte
     * @param float|int|null $timeout
     *
     * @return \Icicle\Coroutine\CoroutineInterface
     */
    public function pipe(
        WritableStreamInterface $stream,
        $endWhenUnreadable = true,
        $length = null,
        $byte = null,
        $timeout = null
    ) {
        return new Coroutine($this->doPipe($stream, $endWhenUnreadable, $length, $byte, $timeout));
    }

    /**
     * @coroutine
     *
     * @param \Icicle\Stream\WritableStreamInterface $stream
     * @param bool $endWhenUnreadable
     * @param int|null $length
     * @param string|int|null $byte
     * @param float|null $timeout
     *
     * @return \Generator
     *
     * @resolve int
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     * @reject \Icicle\Stream\Exception\TimeoutException If the operation times out.
     */
    private function doPipe(WritableStreamInterface $stream, $endWhenUnreadable, $length, $byte, $timeout)
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
                    $data = (yield $this->read($length, $byte, $timeout));

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
