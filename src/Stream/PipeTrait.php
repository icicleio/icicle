<?php
namespace Icicle\Stream;

use Icicle\Coroutine;
use Icicle\Promise;
use Icicle\Stream\Exception\UnwritableException;

trait PipeTrait
{
    /**
     * @see     \Icicle\Stream\ReadableStreamInterface::read()
     *
     * @param   int|null $length
     * @param   string|int|null $byte
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    abstract public function read($length = null, $byte = null);

    /**
     * @see     \Icicle\Stream\ReadableStreamInterface::isReadable()
     *
     * @return  bool
     */
    abstract public function isReadable();

    /**
     * @see     \Icicle\Stream\ParserTrait::parseByte()
     *
     * @param   int|null $length
     */
    abstract protected function parseLength($length);

    /**
     * @see     \Icicle\Stream\ParserTrait::parseByte()
     *
     * @param   string|int|null $byte
     *
     * @return  string|null
     */
    abstract protected function parseByte($byte);

    /**
     * @see     \Icicle\Stream\ReadableStreamInterface::pipe()
     *
     * @param   \Icicle\Stream\WritableStreamInterface $stream
     * @param   bool $endWhenUnreadable
     * @param   int|null $length
     * @param   string|int|null $byte
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function pipe(WritableStreamInterface $stream, $endWhenUnreadable = true, $length = null, $byte = null)
    {
        if (!$stream->isWritable()) {
            return Promise\reject(new UnwritableException('The stream is not writable.'));
        }

        $length = $this->parseLength($length);
        if (0 === $length) {
            return Promise\resolve(0);
        }

        $byte = $this->parseByte($byte);

        $promise = Coroutine\create(function () use ($stream, $length, $byte) {
            $bytes = 0;

            do {
                $data = (yield $this->read($length, $byte));

                $count = strlen($data);
                $bytes += $count;

                yield $stream->write($data);
            } while (
                $this->isReadable()
                && $stream->isWritable()
                && (null === $byte || $data[$count - 1] !== $byte)
                && (null === $length || 0 < $length -= $count)
            );

            yield $bytes;
        });

        if ($endWhenUnreadable) {
            $promise = $promise->cleanup(function () use ($stream) {
                if (!$this->isReadable() && $stream->isWritable()) {
                    $stream->end();
                }
            });
        }

        return $promise;
    }
}
