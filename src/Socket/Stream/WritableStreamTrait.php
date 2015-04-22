<?php
namespace Icicle\Socket\Stream;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Socket\SocketInterface;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Stream\Structures\Buffer;

trait WritableStreamTrait
{
    /**
     * Queue of data to write and promises to resolve when that data is written (or fails to write).
     * Data is stored as an array: [Buffer, int, int|float|null, Deferred].
     *
     * @var \SplQueue
     */
    private $writeQueue;
    
    /**
     * @var bool
     */
    private $writable = true;
    
    /**
     * @var \Icicle\Loop\Events\SocketEventInterface
     */
    private $await;
    
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
     * @param   resource $socket Stream socket resource.
     */
    private function init($socket)
    {
        stream_set_write_buffer($socket, 0);
        stream_set_chunk_size($socket, SocketInterface::CHUNK_SIZE);
        
        $this->writeQueue = new \SplQueue();
        
        $this->await = $this->createAwait($socket);
    }
    
    /**
     * Frees all resources used by the writable stream.
     *
     * @param   \Exception $exception
     */
    private function free(Exception $exception)
    {
        $this->writable = false;
        
        $this->await->free();
        
        while (!$this->writeQueue->isEmpty()) {
            /** @var \Icicle\Promise\Deferred $deferred */
            list( , , , $deferred) = $this->writeQueue->shift();
            $deferred->reject($exception);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function write($data, $timeout = null)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        $data = new Buffer($data);
        
        if ($this->writeQueue->isEmpty()) {
            if ($data->isEmpty()) {
                return Promise::resolve(0);
            }
            
            // Error reporting suppressed since fwrite() emits E_WARNING if the stream buffer is full.
            $written = @fwrite($this->getResource(), $data, SocketInterface::CHUNK_SIZE);
            
            if (false === $written) {
                $message = 'Failed to write to stream.';
                if (null !== ($error = error_get_last())) {
                    $message .= " Errno: {$error['type']}; {$error['message']}";
                }
                $exception = new FailureException($message);
                $this->close($exception);
                return Promise::reject($exception);
            }
            
            if ($data->getLength() <= $written) {
                return Promise::resolve($written);
            }
            
            $data->remove($written);
        } else {
            $written = 0;
        }
        
        $deferred = new Deferred();
        $this->writeQueue->push([$data, $written, $timeout, $deferred]);
        
        if (!$this->await->isPending()) {
            $this->await->listen($timeout);
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * @inheritdoc
     */
    public function end($data = null, $timeout = null)
    {
        $promise = $this->write($data, $timeout);
        
        $this->writable = false;
        
        $promise->after(function () {
            $this->close(new ClosedException('The stream was ended.'));
        });
        
        return $promise;
    }
    
    /**
     * Returns a promise that is fulfilled when the stream is ready to receive data (output buffer is not full).
     *
     * @param   float|int|null $timeout Number of seconds until the returned promise is rejected with a TimeoutException
     *          if the data cannot be written to the stream. Use null for no timeout.
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @resolve int Always resolves with 0.
     *
     * @reject  \Icicle\Stream\Exception\UnwritableException If the stream is no longer writable.
     * @reject  \Icicle\Stream\Exception\ClosedException If the stream has been closed.
     */
    protected function await($timeout = null)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        $deferred = new Deferred();
        $this->writeQueue->push([new Buffer(), 0, $timeout, $deferred]);
        
        if (!$this->await->isPending()) {
            $this->await->listen($timeout);
        }
        
        return $deferred->getPromise();
    }
    
    /**
     * @inheritdoc
     */
    public function isWritable()
    {
        return $this->writable;
    }
    
    /**
     * @param   resource $socket Stream socket resource.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    protected function createAwait($socket)
    {
        return Loop::await($socket, function ($resource, $expired) {
            if ($expired) {
                $this->close(new TimeoutException('Writing to the socket timed out.'));
                return;
            }

            /**
             * @var \Icicle\Stream\Structures\Buffer $data
             * @var \Icicle\Promise\Deferred $deferred
             */
            list($data, $previous, $timeout, $deferred) = $this->writeQueue->shift();
            
            if (!$data->isEmpty()) {
                // Error reporting suppressed since fwrite() emits E_WARNING if the stream buffer is full.
                $written = @fwrite($resource, $data, SocketInterface::CHUNK_SIZE);
                
                if (false === $written || 0 === $written) {
                    $message = 'Failed to write to stream.';
                    if (null !== ($error = error_get_last())) {
                        $message .= " Errno: {$error['type']}; {$error['message']}";
                    }
                    $exception = new FailureException($message);
                    $deferred->reject($exception);
                    $this->close($exception);
                    return;
                }
                
                if ($data->getLength() <= $written) {
                    $deferred->resolve($written + $previous);
                } else {
                    $data->remove($written);
                    $written += $previous;
                    $this->writeQueue->unshift([$data, $written, $timeout, $deferred]);
                }
            } else {
                $deferred->resolve($previous);
            }
            
            if (!$this->writeQueue->isEmpty()) {
                list( , , $timeout) = $this->writeQueue->top();
                $this->await->listen($timeout);
            }
        });
    }
}
