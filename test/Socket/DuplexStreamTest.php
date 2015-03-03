<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\DuplexStream;

class DuplexStreamTest extends StreamTest
{
    use ReadableStreamTestTrait, WritableStreamTestTrait;
    
    public function createStreams()
    {
        list($read, $write) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($write, self::WRITE_STRING); // Make $read readable.
        $readable = new DuplexStream($read);
        $writable = new DuplexStream($write);
        
        return [$readable, $writable];
    }
}
