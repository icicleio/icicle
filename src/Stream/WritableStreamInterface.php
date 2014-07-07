<?php
namespace Icicle\Stream;

interface WritableStreamInterface extends StreamInterface
{
    /**
     * Queues data to be sent on the stream (non-blocking write). If given, the callback will
     * be called once all the data has been written to the stream.
     *
     * @param   string $data
     * @param   callable|null $callback
     *
     * @return  bool Returns true if the data was queued to be sent, false if an error occurred.
     *
     * @api
     */
    public function write($data, callable $callback = null);
    
    /**
     * Queues the data to be sent on the stream and closes the stream once the data has been written.
     * If a callback is given, it will be called once all the data has been written to the stream.
     *
     * @param   string|null $data
     * @param   callable|null $callback
     *
     * @return  bool Returns true if the data was queued to be sent, false if an error occurred.
     *
     * @api
     */
    public function end($data = null, callable $callback = null);
    
    /**
     * Determines if the stream is still writable.
     *
     * @return  bool
     *
     * @api
     */
    public function isWritable();
}
