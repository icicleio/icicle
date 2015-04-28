<?php
namespace Icicle\Socket\Stream;

use Icicle\Promise\Promise;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\WritableStreamInterface;

trait PipeTrait
{
    /**
     * @see     \Icicle\Socket\SocketInterface::isOpen()
     *
     * @return  bool
     */
    abstract public function isOpen();

    /**
     * @see     \Icicle\Socket\Stream\ReadableSocketInterface::read()
     *
     * @param   int|null $length
     * @param   string|int|null $byte
     * @param   float|int|null $timeout
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    abstract public function read($length = null, $byte = null, $timeout = null);

    /**
     * @see     \Icicle\Socket\Stream\ReadableSocketInterface::isReadable()
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
     * @see     \Icicle\Socket\Stream\ReadableSocketInterface::pipe()
     *
     * @param   \Icicle\Stream\WritableStreamInterface $stream
     * @param   bool $endOnClose
     * @param   int|null $length
     * @param   string|int|null $byte
     * @param   float|int|null $timeout
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function pipe(
        WritableStreamInterface $stream,
        $endOnClose = true,
        $length = null,
        $byte = null,
        $timeout = null
    ) {
        if (!$stream->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is not writable.'));
        }

        $length = $this->parseLength($length);
        if (0 === $length) {
            return Promise::resolve(0);
        }

        $byte = $this->parseByte($byte);

        $promise = Promise::iterate(
            function ($data) use (&$length, $stream, $byte, $timeout) {
                static $bytes = 0;
                $count = strlen($data);
                $bytes += $count;

                $promise = $stream->write($data, $timeout);

                if ((null !== $byte && $data[$count - 1] === $byte)
                    || (null !== $length && 0 >= $length -= $count)) {
                    return $promise->then(function () use ($bytes) {
                        return $bytes;
                    });
                }

                return $promise->then(function () use ($stream, $bytes, $length, $byte, $timeout) {
                    if (!$this->isReadable() || !$stream->isWritable()) {
                        return $bytes;
                    }
                    return $this->read($length, $byte, $timeout);
                });
            },
            function ($data) {
                return is_string($data);
            },
            $this->read($length, $byte, $timeout)
        );

        if ($endOnClose) {
            $promise->after(function () use ($stream, $timeout) {
                if (!$this->isReadable()) {
                    $stream->end(null, $timeout);
                }
            });
        }

        return $promise;
    }
}
