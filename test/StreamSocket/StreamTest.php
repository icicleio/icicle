<?php
namespace Icicle\Tests\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\StreamSocket\Stream;
use Icicle\Tests\TestCase;

class StreamTest extends TestCase
{
    const TIMEOUT = 0.1;
    const WRITE_STRING = '1234567890';
    
    protected $readStream;
    
    protected $writeStream;
    
/*
    public function setUp()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        $readable = new Stream($sockets[0], self::TIMEOUT);
        $writable = new Stream($sockets[1], self::TIMEOUT);
    }
*/
    
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function createStreams()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        $readable = new Stream($sockets[0], self::TIMEOUT);
        $writable = new Stream($sockets[1], self::TIMEOUT);
        
        return [$readable, $writable];
    }
    
    public function testGetTimeout()
    {
        $stream = new Stream(fopen('php://memory', 'r+'), self::TIMEOUT);
        
        $this->assertSame(self::TIMEOUT, $stream->getTimeout());
    }
    
    /**
     * @depends testGetTimeout
     */
    public function testConstructMinTimeout()
    {
        $stream = new Stream(fopen('php://memory', 'r+'), -1);
        
        $this->assertSame(Stream::MIN_TIMEOUT, $stream->getTimeout());
    }
    
    public function testRead()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) {
                     return self::WRITE_STRING === (string) $param;
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadAfterClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $readable->close();
        
        $this->assertFalse($readable->isReadable());
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadThenClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testSimultaneousRead()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise1 = $readable->read();
        
        $promise2 = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) {
                     return self::WRITE_STRING === (string) $param;
                 }));
        
        $promise1->done($callback, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceof('Icicle\Stream\Exception\BusyException'));
        
        $promise2->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadWithLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $length = floor(strlen(self::WRITE_STRING) / 2);
        
        $promise = $readable->read($length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) use ($length) {
                     return substr(self::WRITE_STRING, 0, $length) === (string) $param;
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read($length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) use ($length) {
                     return substr(self::WRITE_STRING, $length, $length) === (string) $param;
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadWithInvalidLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read(-1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) {
                     return '' === (string) $param;
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testCancelRead()
    {
        $exception = new Exception();
        
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        $promise->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $promise = $readable->read();
        
        $this->assertTrue($promise->isPending());
    }
    
    /**
     * @medium
     * @depends testRead
     */
    public function testDrainThenRead()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) {
                     return self::WRITE_STRING === (string) $param;
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @medium
     * @depends testRead
     */
    public function testReadOnEmptyStream()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->read(); // Nothing to read on this stream.
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testPoll()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->poll();
        
        $promise->done($this->createCallback(1), $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read(); // Empty the readable stream and ignore data.
        
        Loop::run();
        
        $promise = $readable->poll();
        
        $promise->done($this->createCallback(0), $this->createCallback(0));
    }
    
    /**
     * @depends testPoll
     */
    public function testPollAfterClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $readable->close();
        
        $promise = $readable->poll();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testPoll
     */
    public function testPollThenClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->poll();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadAfterEOF()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        Loop::run(); // Drain readable buffer.
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        fclose($writable->getResource()); // Close other end of pipe.
        
        Loop::run();
    }
    
/*
    public function testReadFailure()
    {
        $socket = stream_socket_client('udp://icicle.io:53');
        
        $stream = new Stream($socket, self::TIMEOUT);
        
        $promise = $stream->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
*/
    
    /**
     * @depends testRead
     */
    public function testWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        Loop::run(); // Remove everything from the readable stream and ignore data.
        
        $string = "{'New String\0To Write'}\r\n";
        
        $promise = $writable->write($string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) use ($string) {
                     return $string === (string) $param;
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteAfterClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->close();
        
        $this->assertFalse($writable->isWritable());
        
        $promise = $writable->write(self::WRITE_STRING);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnwritableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteThenClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->write();
        
        $writable->close();
        
        $this->assertFalse($writable->isWritable());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
    public function testMultipleWritesThenClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $writable->close();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
    public function testEnd()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->end(self::WRITE_STRING);
        
        $this->assertFalse($writable->isWritable());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(self::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $this->assertTrue($writable->isReadable());
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $buffer = '';
        
        for ($i = 0; $i < Stream::CHUNK_SIZE + 1; ++$i) {
            $buffer .= '1';
        }
        
        $promise = $writable->write($buffer);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($buffer)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $readable->read(); // Empty the buffer by reading it.
        
        while ($promise->isPending()) {
            $readable->read(); // Pull more data out of the buffer.
            Loop::tick(true);
        }
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteAfterPendingWriteAfterEOF()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        // Extra write to ensure queue is not empty when onWrite() is called.
        $promise = $writable->write(self::WRITE_STRING);
        
        $readable->close();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testAwait()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->await();
        
        $promise->done($this->createCallback(1), $this->createCallback(0));
        
        Loop::run();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $promise = $writable->await();
        
        $promise->done($this->createCallback(1), $this->createCallback(0));
        
        while ($promise->isPending()) {
            $readable->read(); // Pull more data out of the buffer.
            Loop::tick(true);
        }
    }
    
    /**
     * @depends testAwait
     */
    public function testAwaitAfterClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->close();
        
        $promise = $writable->await();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnwritableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testAwait
     */
    public function testAwaitThenClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->await();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $writable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
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
