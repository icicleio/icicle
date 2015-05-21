<?php
namespace Icicle\Stream;

interface WritableStreamInterface extends StreamInterface
{
    /**
     * Queues data to be sent on the stream. The promise returned is fulfilled once the data has successfully been
     * written to the stream.
     *
     * @param   string $data
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     *
     * @reject  \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject  \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     */
    public function write($data);

    /**
     * Queues the data to be sent on the stream and closes the stream once the data has been written.
     *
     * @param   string|null $data
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve int Number of bytes written to the stream.
     *
     * @reject  \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject  \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     */
    public function end($data = null);
    
    /**
     * Determines if the stream is still writable.
     *
     * @return  bool
     */
    public function isWritable();
}
