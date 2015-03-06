<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\Exception\UnwritableException;
use Icicle\Structures\Buffer;
use SplQueue;

trait WritableStreamTrait
{
    /**
     * Queue of data to write and promises to resolve when that data is written (or fails to write).
     * Data is stored as an array: [Buffer, int, int|float|null, Deferred].
     *
     * @var SplQueue
     */
    private $writeQueue;
    
    /**
     * @var bool
     */
    private $writable = true;
    
    /**
     * @var AwaitInterface
     */
    private $await;
    
    /**
     * @return  resource Socket resource.
     */
    abstract protected function getResource();
    
    /**
     * Closes the socket if it is still open.
     *
     * @param   Exception|null $exception
     */
    abstract public function close(Exception $exception = null);
    
    /**
     * @param   resource $socket Socket resource.
     */
    private function init($socket)
    {
        stream_set_write_buffer($socket, 0);
        stream_set_chunk_size($socket, self::CHUNK_SIZE);
        
        $this->writeQueue = new SplQueue();
        
        $this->await = Loop::await($socket, function ($resource, $expired) {
            if ($expired) {
                $this->close(new TimeoutException('Writing to the socket timed out.'));
                return;
            }
            
            list($data, $previous, $timeout, $deferred) = $this->writeQueue->shift();
            
            if (!$data->isEmpty()) {
                // Error reporting suppressed since fwrite() emits E_WARNING if the stream buffer is full.
                $written = @fwrite($resource, $data, self::CHUNK_SIZE);
                
                if (false === $written || 0 === $written) {
                    $message = 'Failed to write to stream.';
                    $error = error_get_last();
                    if (null !== $error) {
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
    
    /**
     * Frees all resources used by the writable stream.
     *
     * @param   Exception $exception
     */
    private function free(Exception $exception)
    {
        $this->writable = false;
        
        $this->await->free();
        
        while (!$this->writeQueue->isEmpty()) {
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
            $written = @fwrite($this->getResource(), $data, self::CHUNK_SIZE);
            
            if (false === $written) {
                $message = 'Failed to write to stream.';
                $error = error_get_last();
                if (null !== $error) {
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
     * @inheritdoc
     */
    public function await($timeout = null)
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
}
