<?php
namespace Icicle\Stream;

interface ReadableStreamInterface extends StreamInterface
{
    /**
     * @param   int|null $length Max number of bytes to read. Fewer bytes may be returned. Use null to read as much data
     *          as possible.
     * @param   string|int|null $byte Reading will stop once the given byte occurs in the stream. Note that reading may
     *          stop before the byte is found in the stream. The search byte will be included in the resolving string.
     *          Use null to effectively ignore this parameter and read any bytes.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve string Data read from the stream.
     *
     * @reject  \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject  \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject  \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     *
     * @api
     */
    public function read($length = null, $byte = null);

    /**
     * Returns a promise that is fulfilled when there is data available to read, without actually consuming any data.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve string Empty string.
     *
     * @reject  \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject  \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject  \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     *
     * @api
     */
    public function poll();
    
    /**
     * Determines if the stream is still readable. A stream being readable does not mean there is data immediately
     * available to read. Use read() or poll() to wait for data.
     *
     * @return  bool
     *
     * @api
     */
    public function isReadable();
    
    /**
     * Pipes data read on this stream into the given writable stream destination.
     *
     * @param   WritableStreamInterface $stream
     * @param   bool $endOnClose Set to true to automatically end the writable stream when the readable stream closes.
     * @param   int|null $length If not null, only $length bytes will be piped.
     * @param   string|int $byte Piping will stop once the given byte occurs in the stream. The search character will
     *          be piped to the writable stream string. Use null to effectively ignore this parameter and pipe all bytes.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve int Resolves when the writable stream closes or once $length bytes (if $length was not null) have been
     *          piped to the stream.
     *
     * @reject  \Icicle\Stream\Exception\BusyException If a read was already pending on the stream.
     * @reject  \Icicle\Stream\Exception\UnreadableException If the stream is no longer readable.
     * @reject  \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     *
     * @api
     */
    public function pipe(WritableStreamInterface $stream, $endOnClose = true, $length = null, $byte = null);
}
