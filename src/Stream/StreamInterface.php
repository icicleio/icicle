<?php
namespace Icicle\Stream;

interface StreamInterface
{
    /**
     * Closes the stream, making it unreadable or unwritable.
     *
     * @api
     */
    public function close();
}
