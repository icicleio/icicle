<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\WritableStream;

class WritableStreamTest extends StreamTest
{
    use WritableStreamTestTrait;
    
    public function createStreams()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        $readable = $this->getMockBuilder('Icicle\Socket\ReadableStream')
                         ->disableOriginalConstructor()
                         ->getMock();
        $readable->method('getResource')
                 ->will($this->returnValue($sockets[0]));
        $writable = new WritableStream($sockets[1]);
        
        return [$readable, $writable];
    }
}