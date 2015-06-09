<?php
namespace Icicle\Socket\Stream;

use Exception;
use Icicle\Socket\Socket;

class WritableStream extends Socket implements WritableSocketInterface
{
    use WritableStreamTrait;
    
    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->init($socket);
    }

    /**
     * Frees resources associated with the stream and closes the stream.
     *
     * @param \Exception|null $exception Reason for the stream closing.
     */
    protected function free(Exception $exception = null)
    {
        $this->detach($exception);
        parent::close();
    }
}
