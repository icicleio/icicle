<?php
namespace Icicle\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Promise\Promise;
use Icicle\Socket\Exception\ClosedException;
use Icicle\Socket\Exception\FailureException;
use Icicle\Socket\Exception\TimeoutException;
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
            
            $written = @fwrite($resource, $data, self::CHUNK_SIZE);
            
            if (false === $written || (0 === $written && !$data->isEmpty())) {
                $exception = new FailureException('Failed to write to stream.');
                $deferred->reject($exception);
                $this->close($exception);
                return;
            }
            
            if ($data->isEmpty()) {
                $deferred->resolve($written + $previous);
            } else {
                $written += $previous;
                $data->remove($written);
                $this->writeQueue->unshift([$data, $written, $timeout, $deferred]);
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
     * {@inheritdoc}
     */
    public function write($data, $timeout = null)
    {
        if (!$this->isWritable()) {
            return Promise::reject(new UnwritableException('The stream is no longer writable.'));
        }
        
        $data = new Buffer($data);
        
        if ($this->writeQueue->isEmpty() && !$data->isEmpty()) {
            $written = @fwrite($this->getResource(), $data, self::CHUNK_SIZE);
            
            if (false === $written) {
                $exception = new FailureException('Failed to write to stream.');
                $this->close($exception);
                return Promise::reject($exception);
            }
            
            if ($data->getLength() === $written) {
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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function await($timeout = null)
    {
        return $this->write(null, $timeout);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isWritable()
    {
        return $this->writable;
    }
}
