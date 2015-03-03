<?php
namespace Icicle\Tests\Socket;

use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\ReadableStream;

class ReadableStreamTest extends StreamTest
{
    use ReadableStreamTestTrait;
    
    public function createStreams()
    {
        list($read, $write) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($write, self::WRITE_STRING); // Make $read readable.
        
        $readable = new ReadableStream($read);
        
        $writable = $this->getMockBuilder('Icicle\Socket\WritableStream')
                         ->disableOriginalConstructor()
                         ->getMock();
        
        stream_set_blocking($write, 0);
        
        $writable->method('getResource')
                 ->will($this->returnValue($write));
        
        $writable->method('isWritable')
                 ->will($this->returnValue(true));
        
        $writable->method('write')
                 ->will($this->returnCallback(function ($data) use ($write) {
                     $length = strlen($data);
                     if ($length) {
                        fwrite($write, $data);
                     }
                     return Promise::resolve($length);
                 }));
        
        $writable->method('close')
                 ->will($this->returnCallback(function () use ($write) {
                     fclose($write);
                 }));
        
        return [$readable, $writable];
    }
    
    public function testReadAfterEof()
    {
        list($readable, $writable) = $this->createStreams();
        
        fclose($writable->getResource()); // Close other end of pipe.
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run(); // Drain readable buffer.
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\EofException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testReadToAfterEof()
    {
        list($readable, $writable) = $this->createStreams();
        
        fclose($writable->getResource()); // Close other end of pipe.
        
        $promise = $readable->readTo("\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run(); // Drain readable buffer.
        
        $promise = $readable->readTo("\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\EofException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
}