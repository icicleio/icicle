<?php
namespace Icicle\Tests\Socket\Stream;

use Icicle\Loop;
use Icicle\Promise;
use Icicle\Socket\Stream\ReadableStream;
use Icicle\Tests\Stream\ReadableStreamTestTrait;

class ReadableStreamTest extends StreamTest
{
    use ReadableStreamTestTrait, ReadableSocketTestTrait;
    
    public function createStreams()
    {
        list($read, $write) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        
        $readable = new ReadableStream($read);
        
        $writable = $this->getMockBuilder('Icicle\Socket\Stream\WritableStream')
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
                return Promise\resolve($length);
            }));
        
        $writable->method('close')
                 ->will($this->returnCallback(function () use ($write) {
                     fclose($write);
                 }));
        
        return [$readable, $writable];
    }
}