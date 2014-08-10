<?php
namespace Icicle\Stream;

interface WritableStreamInterface extends StreamInterface
{
    /**
     * Queues data to be sent on the stream. The promise returned is fulfilled once the data has
     * successfully been written to the stream.
     *
     * @param   string|null $data
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function write($data = null);
    
    /**
     * Returns a promise that is fulfilled when the stream is ready to receive data.
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function await();
    
    /**
     * Queues the data to be sent on the stream and closes the stream once the data has been written.
     *
     * @param   string|null $data
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function end($data = null);
    
    /**
     * Determines if the stream is still writable.
     *
     * @return  bool
     *
     * @api
     */
    public function isWritable();
}
