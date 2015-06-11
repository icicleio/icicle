<?php
namespace Icicle\Socket\Stream;

use Icicle\Socket\SocketInterface;
use Icicle\Stream\ReadableStreamInterface;
use Icicle\Stream\WritableStreamInterface;

interface ReadableSocketInterface extends SocketInterface, ReadableStreamInterface
{
    /**
     * @param int|null $length Max number of bytes to read. Fewer bytes may be returned. Use null to read as much data
     *     as possible.
     * @param string|int|null $byte Reading will stop once the given byte occurs in the stream. Note that reading may
     *     stop before the byte is found in the stream. The search byte will be included in the resolving string.
     *     Use null to effectively ignore this parameter and read any bytes.
     * @param float|int|null $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *     if no data is received. Use null for no timeout.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve string Data read from the stream.
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     * @reject \Icicle\Stream\Exception\TimeoutException If the operation times out.
     */
    public function read($length = null, $byte = null, $timeout = null);

    /**
     * Pipes data read on this stream into the given writable stream destination.
     *
     * @param \Icicle\Stream\WritableStreamInterface $stream
     * @param bool $endWhenUnreadable Set to true to automatically end the writable stream when the readable stream
     *     is no longer readable.
     * @param int|null $length If not null, only $length bytes will be piped.
     * @param string|int $byte Piping will stop once the given byte occurs in the stream. The search character will
     *     be piped to the writable stream string. Use null to effectively ignore this parameter and pipe all bytes.
     * @param float|int|null $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *     if no data is received. Use null for no timeout.
     *
     * @return \Icicle\Coroutine\CoroutineInterface
     *
     * @resolve int Resolves when the writable stream closes or once $length bytes (if $length was not null) have been
     *     piped to the stream.
     *
     * @reject \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     * @reject \Icicle\Stream\Exception\TimeoutException If the operation times out.
     */
    public function pipe(
        WritableStreamInterface $stream,
        $endWhenUnreadable = true,
        $length = null,
        $byte = null,
        $timeout = null
    );
}
