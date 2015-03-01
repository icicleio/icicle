<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\DuplexStream;

class DuplexStreamTest extends StreamTest
{
    use ReadableStreamTestTrait, WritableStreamTestTrait;
    
    public function createStreams()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        $readable = new DuplexStream($sockets[0]);
        $writable = new DuplexStream($sockets[1]);
        
        return [$readable, $writable];
    }
}
