<?php
namespace Icicle\Socket\Stream;

use Icicle\Socket\SocketInterface;
use Icicle\Stream\WritableStreamInterface;

interface WritableSocketInterface extends SocketInterface, WritableStreamInterface
{
    /**
     * Queues data to be sent on the stream. The promise returned is fulfilled once the data has successfully been
     * written to the stream.
     *
     * @param string $data Data to write to the stream.
     * @param float|int $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *     if the data cannot be written to the stream. Use 0 for no timeout.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     *
     * @reject \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     */
    public function write($data, $timeout = 0);

    /**
     * Queues the data to be sent on the stream and closes the stream once the data has been written.
     *
     * @param string $data Data to write to the stream or null.
     * @param float|int $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *     if the data cannot be written to the stream. Use 0 for no timeout.
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     *
     * @reject \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     */
    public function end($data = '', $timeout = 0);
}
