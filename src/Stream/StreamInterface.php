<?php
namespace Icicle\Stream;

interface StreamInterface
{
    /**
     * Determines if the stream is still open.
     *
     * @return bool
     */
    public function isOpen();
    
    /**
     * Closes the stream, making it unreadable or unwritable.
     */
    public function close();
}
