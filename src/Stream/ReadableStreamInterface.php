<?php
namespace Icicle\Stream;

interface ReadableStreamInterface extends StreamInterface
{
    /**
     * @param   int|null $length Use null to return all data available.
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function read($length = null);
    
    /**
     * Determines if there is information in the stream to read.
     *
     * @return  bool
     *
     * @api
     */
    public function isReadable();
    
    /**
     * @param   string $data Data to reinsert at the front of the read buffer.
     *
     * @api
     */
    public function unshift($data);
    
    /**
     * Pauses reading data on the socket.
     *
     * @api
     */
    public function pause();
    
    /**
     * Resumes reading data on the socket.
     *
     * @api
     */
    public function resume();
    
    /**
     * Determines if the stream is socket.
     *
     * @return  bool
     *
     * @api
     */
    public function isPaused();
    
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
    public function pipe(WritableStreamInterface $stream, $end = true);
    
    /**
     * Stops piping data from this stream to the current destination.
     *
     * @api
     */
    public function unpipe();
}
