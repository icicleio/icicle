<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\ReadableStream;

class ReadableStreamTest extends StreamTest
{
    use ReadableStreamTestTrait;
    
    public function createStreams()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        $readable = new ReadableStream($sockets[0]);
        $writable = $this->getMockBuilder('Icicle\Socket\WritableStream')
                         ->disableOriginalConstructor()
                         ->getMock();
        $writable->method('getResource')
                 ->will($this->returnValue($sockets[1]));
        
        return [$readable, $writable];
    }
}