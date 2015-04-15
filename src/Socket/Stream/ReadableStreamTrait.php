<?php
namespace Icicle\Socket\Stream;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\EofException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Stream\Exception\BusyException;
use Icicle\Socket\SocketInterface;
use Icicle\Stream\Exception\UnreadableException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\ParserTrait;
use Icicle\Stream\WritableStreamInterface;

trait ReadableStreamTrait
{
    use ParserTrait;
    use PipeTrait;

    /**
     * @var \Icicle\Promise\Deferred|null
     */
    private $deferred;
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface
     */
    private $poll;
    
    /**
     * @var int
     */
    private $length = 0;
    
    /**
     * @var string|null
     */
    private $byte;
    
    /**
     * @return  resource Stream socket resource.
     */
    abstract protected function getResource();

    /**
     * Closes the socket if it is still open.
     *
     * @param   \Exception|null $exception
     */
    abstract public function close(Exception $exception = null);
    
    /**
     * @param  resource $socket Stream socket resource.
     */
    private function init($socket)
    {
        stream_set_read_buffer($socket, 0);
        stream_set_chunk_size($socket, SocketInterface::CHUNK_SIZE);
        
        $this->poll = $this->createPoll($socket);
    }
    
    /**
     * Frees all resources used by the writable stream.
     *
     * @param   \Exception $exception
     */
    private function free(Exception $exception)
    {
        $this->poll->free();
        
        if (null !== $this->deferred) {
            $this->deferred->reject($exception);
            $this->deferred = null;
        }
    }

    /**
     * @inheritdoc
     */
    public function read($length = null, $byte = null, $timeout = null)
    {
        if (null !== $this->deferred) {
            return Promise::reject(new BusyException('Already waiting on stream.'));
        }
        
        if (!$this->isReadable()) {
            return Promise::reject(new UnreadableException('The stream is no longer readable.'));
        }

        $this->length = $this->parseLength($length);
        if (null === $this->length) {
            $this->length = SocketInterface::CHUNK_SIZE;
        }

        $this->byte = $this->parseByte($byte);
        
        $this->poll->listen($timeout);
        
        $this->deferred = new Deferred(function () {
            $this->poll->cancel();
            $this->deferred = null;
        });
        
        return $this->deferred->getPromise();
    }
    
    /**
     * @inheritdoc
     */
    public function poll($timeout = null)
    {
        return $this->read(0, null, $timeout);
    }
    
    /**
     * @inheritdoc
     */
    public function isReadable()
    {
        return $this->isOpen();
    }

    /**
     * @param   resource $socket Stream socket resource.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    protected function createPoll($socket)
    {
        return Loop::poll($socket, function ($resource, $expired) {
            if ($expired) {
                $this->deferred->reject(new TimeoutException('The connection timed out.'));
                $this->deferred = null;
                return;
            }

            $data = '';

            if (0 === $this->length) {
                $this->deferred->resolve($data);
                $this->deferred = null;
                return;
            }

            if (null !== $this->byte) {
                for ($i = 0; $i < $this->length; ++$i) {
                    if (false === ($byte = fgetc($resource))) {
                        break;
                    }
                    $data .= $byte;
                    if ($byte === $this->byte) {
                        break;
                    }
                }
            } else {
                $data = (string) fread($resource, $this->length);
            }

            $this->deferred->resolve($data);
            $this->deferred = null;

            if ('' === $data && feof($resource)) { // Close only if no data was read and at EOF.
                $this->close(new EofException('Connection reset by peer or reached EOF.'));
            }
        });
    }
}
