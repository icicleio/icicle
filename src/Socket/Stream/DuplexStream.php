<?php
namespace Icicle\Socket\Stream;

use Exception;
use Icicle\Socket\Socket;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\DuplexStreamInterface;

class DuplexStream extends Socket implements DuplexStreamInterface
{
    use ReadableStreamTrait, WritableStreamTrait {
        ReadableStreamTrait::init insteadof WritableStreamTrait;
        ReadableStreamTrait::free insteadof WritableStreamTrait;
        ReadableStreamTrait::init as initReadable;
        ReadableStreamTrait::free as freeReadable;
        WritableStreamTrait::init as initWritable;
        WritableStreamTrait::free as freeWritable;
    }
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        $this->initReadable($socket);
        $this->initWritable($socket);
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
        
        $this->freeReadable($exception);
        $this->freeWritable($exception);
        
        parent::close();
    }
}
