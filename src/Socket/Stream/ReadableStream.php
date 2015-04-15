<?php
namespace Icicle\Socket\Stream;

use Exception;
use Icicle\Socket\Socket;
use Icicle\Stream\Exception\ClosedException;

class ReadableStream extends Socket implements ReadableSocketInterface
{
    use ReadableStreamTrait;
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        $this->init($socket);
    }
    
    /**
     * Closes the stream.
     *
     * @param   \Exception|null $exception Reason for the stream closing.
     */
    public function close(Exception $exception = null)
    {
        if (null === $exception) {
            $exception = new ClosedException('The connection was closed.');
        }
        
        $this->free($exception);
        
        parent::close();
    }
}
