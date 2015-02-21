<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Stream\DuplexStreamInterface;

class DuplexStream extends Socket implements DuplexStreamInterface
{
    use ReadableStreamTrait, WritableStreamTrait {
        ReadableStreamTrait::init insteadof WritableStreamTrait;
        ReadableStreamTrait::free insteadof WritableStreamTrait;
        ReadableStreamTrait::init as readInit;
        ReadableStreamTrait::free as readFree;
        WritableStreamTrait::init as writeInit;
        WritableStreamTrait::free as writeFree;
    }
    
    /**
     * @param   resource $socket
     */
    public function __construct($socket)
    {
        parent::__construct($socket);
        
        $this->readInit($socket);
        $this->writeInit($socket);
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
            
            $this->readFree($exception);
            $this->writeFree($exception);
        }
        
        parent::close();
    }
}
