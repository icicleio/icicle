<?php
namespace Icicle\Stream;

interface ReadableStreamInterface extends StreamInterface
{
    /**
     * @param int $length Max number of bytes to read. Fewer bytes may be returned. Use 0 to read as much data
     *     as possible.
     * @param string|int|null $byte Reading will stop once the given byte occurs in the stream. Note that reading may
     *     stop before the byte is found in the stream. The search byte will be included in the resolving string.
     *     Use null to effectively ignore this parameter and read any bytes.
     * @param float|int $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *     if no data is received. Use 0 for no timeout.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve string Data read from the stream.
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream is unexpectedly closed.
     * @reject \Icicle\Promise\Exception\TimeoutException If the operation times out.
     */
    public function read($length = 0, $byte = null, $timeout = 0);

    /**
     * Determines if the stream is still readable. A stream being readable does not mean there is data immediately
     * available to read. Use read() or poll() to wait for data.
     *
     * @return bool
     */
    public function isReadable();
    
    /**
     * @coroutine
     *
     * Pipes data read on this stream into the given writable stream destination.
     *
     * @param WritableStreamInterface $stream
     * @param bool $endWhenUnreadable Set to true to automatically end the writable stream when the readable stream
     *     is no longer readable.
     * @param int $length If not null, only $length bytes will be piped.
     * @param string|int $byte Piping will stop once the given byte occurs in the stream. The search character will
     *     be piped to the writable stream string. Use null to ignore this parameter and pipe all bytes.
     * @param float|int $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *     if no data is received. Use 0 for no timeout.
     *
     * @return \Generator
     *
     * @resolve int Resolves when the writable stream closes or once $length bytes (if $length was not null) have been
     *     piped to the stream.
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream is unexpectedly closed.
     * @reject \Icicle\Promise\Exception\TimeoutException If the operation times out.
     */
    public function pipe(WritableStreamInterface $stream, $endWhenUnreadable = true, $length = 0, $byte = null, $timeout = 0);
}
