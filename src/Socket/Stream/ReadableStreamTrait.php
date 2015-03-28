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
     * Determines if the stream is still open.
     *
     * @return  bool
     */
    abstract public function isOpen();
    
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
    public function read($length = null, $timeout = null)
    {
        return $this->readTo(null, $length, $timeout);
    }
    
    /**
     * @inheritdoc
     */
    public function readTo($byte, $length = null, $timeout = null)
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
        return $this->readTo(null, 0, $timeout);
    }
    
    /**
     * @inheritdoc
     */
    public function isReadable()
    {
        return $this->isOpen();
    }
    
    /**
     * @inheritdoc
     */
    public function pipe(WritableStreamInterface $stream, $endOnClose = true, $length = null, $timeout = null)
    {
        return $this->pipeTo($stream, null, $endOnClose, $length, $timeout);
    }
    
    /**
     * @inheritdoc
     */
    public function pipeTo(WritableStreamInterface $stream, $byte, $endOnClose = true, $length = null, $timeout = null)
    {
        if (!$stream->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is not writable.'));
        }

        $length = $this->parseLength($length);
        if (0 === $length) {
            return Promise::resolve(0);
        }

        $byte = $this->parseByte($byte);

        $promise = Promise::iterate(
            function ($data) use (&$length, $stream, $byte, $timeout) {
                static $bytes = 0;
                $count = strlen($data);
                $bytes += $count;

                $promise = $stream->write($data, $timeout);

                if ((null !== $byte && $data[$count - 1] === $byte) ||
                    (null !== $length && 0 >= $length -= $count)) {
                    return $promise->always(function () use ($bytes) {
                        return $bytes;
                    });
                }

                return $promise->then(
                    function () use ($byte, $length, $timeout) {
                        return $this->readTo($byte, $length, $timeout);
                    },
                    function () use ($bytes) {
                        return $bytes;
                    }
                );
            },
            function ($data) {
                return is_string($data);
            },
            $this->readTo($byte, $length, $timeout)
        );

        if ($endOnClose) {
            $promise->done(null, function () use ($stream) {
                if (!$this->isOpen()) {
                    $stream->end();
                }
            });
        }

        return $promise;
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
