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
     * @inheritdoc
     */
    public function close()
    {
        $this->free(new ClosedException('The connection was closed.'));
    }
    
    /**
     * Frees resources associated with the stream and closes the stream.
     *
     * @param   \Exception $exception Reason for the stream closing.
     */
    public function free(Exception $exception)
    {
        $this->detach($exception);
        
        parent::close();
    }
}
