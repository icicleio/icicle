<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\WritableStreamInterface;

class WritableStream extends Socket implements WritableStreamInterface
{
    use WritableStreamTrait;
    
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
     * @param   Exception|null $exception Reason for the stream closing.
     */
    public function close(Exception $exception = null)
    {
        if ($this->isOpen()) {
            if (null === $exception) {
                $exception = new ClosedException('The connection was closed.');
            }
            
            $this->free($exception);
        }
        
        parent::close();
    }
}
