<?php
namespace Icicle\Stream;

interface ReadableStreamInterface extends StreamInterface
{
    /**
     * @param   int|null $length Max number of bytes to read. Fewer bytes may be returned.
     *          Use null to read as much data as possible.
     *
     * @return  PromiseInterface
     * @resolve string
     * @reject  BusyException|UnreadableException
     *
     * @api
     */
    public function read($length = null);
    
    /**
     * Returns a promise that is fulfilled when there is data available to read, without
     * actually consuming any data.
     *
     * @return  PromiseInterface
     * @resolve string Empty string
     * @reject  BusyException|UnreadableException
     *
     * @api
     */
    public function poll();
    
    /**
     * Determines if the stream is still readable. A stream being readable does not mean
     * there is data immediately available to read. Use read() or poll() to wait for data.
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
     * @param   bool $autoEnd
     *
     * @return  PromiseInterface
     *
     * @api
     */
    //public function pipe(WritableStreamInterface $stream, $end = true);
    
    /**
     * Stops piping data from this stream to the current destination.
     *
     * @api
     */
    //public function unpipe();
}
