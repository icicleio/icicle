<?php
namespace Icicle\Stream;

interface SeekableStreamInterface extends StreamInterface
{
    /**
     * Moves the pointer to a new position in the stream.
     *
     * @param int $offset Number of bytes to seek. Usage depends on value of $whence.
     * @param int $whence Values identical to $whence values for fseek().
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve int New pointer position.
     *
     * @reject \Icicle\Stream\InvalidArgumentException If the whence value is invalid.
     * @reject \Icicle\Stream\OutOfBoundsException If the new offset would be outside the stream.
     * @reject \Icicle\Stream\RuntimeException If seeking fails.
     * @reject \Icicle\Stream\UnseekableException If the stream is no longer seekable (due to being closed or for
     *     another reason).
     * @reject \Icicle\Stream\BusyException If the stream was already waiting on a read or seek operation.
     */
    public function seek($offset, $whence = SEEK_SET);

    /**
     * Current pointer position. Value returned may not reflect the future pointer position if a read, write, or seek
     * operation is pending.
     *
     * @return int
     */
    public function tell();

    /**
     * Returns the total length of the stream if known, otherwise null. Value returned may not reflect a pending write
     * operation.
     *
     * @return int|null
     */
    public function getLength();
}
