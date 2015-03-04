<?php
namespace Icicle\Tests\Socket;

use Icicle\Socket\DuplexStream;
use Icicle\Tests\Stream\ReadableStreamTestTrait;
use Icicle\Tests\Stream\WritableBufferedStreamTestTrait;
use Icicle\Tests\Stream\WritableStreamTestTrait;

class DuplexStreamTest extends StreamTest
{
    use ReadableStreamTestTrait,
        ReadableSocketTestTrait,
        WritableStreamTestTrait,
        WritableBufferedStreamTestTrait,
        WritableSocketTestTrait;
    
    public function createStreams()
    {
        list($read, $write) = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $readable = new DuplexStream($read);
        $writable = new DuplexStream($write);
        
        return [$readable, $writable];
    }
}
