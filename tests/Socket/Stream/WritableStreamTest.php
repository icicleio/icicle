<?php
namespace Icicle\Tests\Socket\Stream;

use Icicle\Loop;
use Icicle\Promise;
use Icicle\Socket\Stream\ReadableStream;
use Icicle\Socket\Stream\WritableStream;
use Icicle\Tests\Stream\WritableBufferedStreamTestTrait;
use Icicle\Tests\Stream\WritableStreamTestTrait;

class WritableStreamTest extends StreamTest
{
    use WritableStreamTestTrait, WritableBufferedStreamTestTrait, WritableSocketTestTrait;
    
    public function createStreams()
    {
        list($read, $write) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        
        $readable = $this->getMockBuilder(ReadableStream::class)
                         ->disableOriginalConstructor()
                         ->getMock();
        
        stream_set_blocking($read, 0);
        
        $readable->method('getResource')
                 ->will($this->returnValue($read));
        
        $readable->method('isReadable')
                 ->will($this->returnValue(true));
        
        $readable->method('read')
            ->will($this->returnCallback(function ($length = 0) use ($read) {
                if (0 === $length) {
                    $length = 8192;
                }
                return Promise\resolve(fread($read, $length));
            }));

        $readable->method('close')
                 ->will($this->returnCallback(function () use ($read) {
                     fclose($read);
                 }));
        
        $writable = new WritableStream($write);
        
        return [$readable, $writable];
    }
    
}