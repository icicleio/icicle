<?php
namespace Icicle\Socket\Stream;

use Exception;
use Icicle\Socket\Socket;
use Icicle\Stream\Exception\ClosedException;

class DuplexStream extends Socket implements DuplexSocketInterface
{
    use ReadableStreamTrait, WritableStreamTrait {
        ReadableStreamTrait::init insteadof WritableStreamTrait;
        ReadableStreamTrait::detach insteadof WritableStreamTrait;
        ReadableStreamTrait::init as initReadable;
        ReadableStreamTrait::detach as detachReadable;
        WritableStreamTrait::init as initWritable;
        WritableStreamTrait::detach as detachWritable;
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
        $this->detachReadable($exception);
        $this->detachWritable($exception);
        
        parent::close();
    }
}
