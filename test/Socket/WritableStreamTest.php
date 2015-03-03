<?php
namespace Icicle\Tests\Socket;

use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Socket\WritableStream;

class WritableStreamTest extends StreamTest
{
    use WritableStreamTestTrait;
    
    public function createStreams()
    {
        list($read, $write) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($write, self::WRITE_STRING); // Make $read readable.
        
        $readable = $this->getMockBuilder('Icicle\Socket\ReadableStream')
                         ->disableOriginalConstructor()
                         ->getMock();
        
        stream_set_blocking($read, 0);
        
        $readable->method('getResource')
                 ->will($this->returnValue($read));
        
        $readable->method('isReadable')
                 ->will($this->returnValue(true));
        
        $readable->method('read')
                 ->will($this->returnCallback(function ($length = null) use ($read) {
                     if (null === $length) {
                         $length = 8192;
                     }
                     return Promise::resolve(fread($read, $length));
                 }));
        
        $readable->method('close')
                 ->will($this->returnCallback(function () use ($read) {
                     fclose($read);
                 }));
        
        $writable = new WritableStream($write);
        
        return [$readable, $writable];
    }
    
    public function testWriteFailure()
    {
        list($readable, $writable) = $this->createStreams();
        
        // Use fclose() manually since $writable->close() will prevent behavior to be tested.
        fclose($writable->getResource());
        
        $promise = $writable->write(self::WRITE_STRING);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
}