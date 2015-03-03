<?php
namespace Icicle\Tests\Socket;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;

trait ReadableStreamTestTrait
{
    /**
     * @return  [ReadableStreamInterface, WritableStreamInterface]
     */
    abstract public function createStreams();
    
    public function testRead()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
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
                 ->with($this->identicalTo(self::WRITE_STRING));
        
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
                 ->with($this->identicalTo(substr(self::WRITE_STRING, 0, $length)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read($length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, $length, $length)));
        
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
                     return empty($param);
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
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadOnEmptyStream()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read(); // Drain stream.
        
        Loop::tick();
        
        $promise = $readable->read(null, self::TIMEOUT); // Nothing to read on this stream.
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReadOnEmptyStream
     */
    public function testDrainThenRead()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read(null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $string = "This is a string to write.\n";
        
        $promise = $writable->write($string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $promise = $readable->read(null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($string));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadTo()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 5;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $promise = $readable->readTo($char);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, 0, $offset + 1)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRead
     */
    public function testReadToIntegerByte()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 5;
        $byte = unpack('C', substr(self::WRITE_STRING, $offset, 1));
        $byte = $byte[1];
        
        $promise = $readable->readTo($byte);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, 0, $offset + 1)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToMultibyteString()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 5;
        $length = 3;
        $string = substr(self::WRITE_STRING, $offset, $length);
        
        $promise = $readable->readTo($string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, 0, $offset + 1)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToNoMatchInStream()
    {
        list($readable, $writable) = $this->createStreams();
        
        $char = '~';
        
        $promise = $readable->readTo($char);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->readTo($char);
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToEmptyString()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->readTo('');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToAfterClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $readable->close();
        
        $this->assertFalse($readable->isReadable());
        
        $promise = $readable->readTo("\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToThenClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->readTo("\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testSimultaneousReadTo()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise1 = $readable->readTo("\0");
        
        $promise2 = $readable->readTo("\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise1->done($callback, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceof('Icicle\Stream\Exception\BusyException'));
        
        $promise2->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToWithLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 10;
        $length = 5;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $promise = $readable->readTo($char, $length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, 0, $length)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->readTo($char);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, $length, $offset - $length + 1)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToWithInvalidLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 5;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $promise = $readable->readTo($char, -1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($param) {
                     return empty($param);
                 }));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testCancelReadTo()
    {
        $exception = new Exception();
        
        list($readable, $writable) = $this->createStreams();
        
        $char = substr(self::WRITE_STRING, 0, 1);
        
        $promise = $readable->readTo($char);
        
        $promise->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $promise = $readable->readTo($char);
        
        $this->assertTrue($promise->isPending());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($char));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToOnEmptyStream()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read(); // Drain stream.
        
        Loop::tick();
        
        $promise = $readable->readTo("\n", null, self::TIMEOUT); // Nothing to read on this stream.
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReadToOnEmptyStream
     */
    public function testDrainThenReadTo()
    {
        list($readable, $writable) = $this->createStreams();
        
        $char = "\n";
        
        $promise = $readable->read();
        
        Loop::run();
        
        $promise = $readable->readTo($char, null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $string1 = "This is a string to write.\n";
        $string2 = "This part should not be read.\n";
        
        $writable->write($string1 . $string2);
        
        $promise = $readable->readTo($char, null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($string1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadAfterReadTo()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 5;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $promise = $readable->readTo($char);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, 0, $offset + 1)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(substr(self::WRITE_STRING, $offset + 1)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadAfterCancelledReadTo()
    {
        $exception = new Exception();
        
        list($readable, $writable) = $this->createStreams();
        
        $offset = 5;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $promise = $readable->readTo($char);
        
        $promise->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(self::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
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
    public function testPipe()
    {
        list($readable, $writable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->exactly(3))
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 static $count = 0;
                 ++$count;
                 $this->assertSame(self::WRITE_STRING, $data);
                 if (3 > $count) {
                     return Promise::resolve(strlen($data));
                 } else {
                     return Promise::reject(new Exception());
                 }
             }));
        
        $promise = $readable->pipe($mock);
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        $writable->write(self::WRITE_STRING);
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        $writable->write(self::WRITE_STRING);
        
        Loop::tick();
        
        $this->assertTrue($promise->isFulfilled());
        $this->assertSame(strlen(self::WRITE_STRING) * 3, $promise->getResult());
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeOnUnwritableStream()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(false));
        
        $promise = $readable->pipe($mock);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnwritableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeEndOnClose()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->once())
             ->method('end');
        
        $promise = $readable->pipe($mock, true);
        
        $promise->done($this->createCallback(0), $this->createCallback(1));
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeDoNotEndOnClose()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false);
        
        $promise->done($this->createCallback(0), $this->createCallback(1));
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeCancel()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock);
        
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        
        $promise->cancel($exception);
        
        Loop::run();
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeWithLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $length = 8;
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($length) {
                 $this->assertSame(substr(self::WRITE_STRING, 0, $length), $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, $length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($length));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::tick();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->exactly(2))
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, strlen(self::WRITE_STRING));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(self::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        
        $writable->write(self::WRITE_STRING);
        
        Loop::tick();
        
        $this->assertFalse($promise->isPending());
    }
    
    /**
     * @depends testPipeWithLength
     */
    public function testPipeWithInvalidLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->never())
             ->method('write');
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, -1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::tick();
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeTimeout()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
    
    /**
     * @depends testPipeWithLength
     * @depends testPipeTimeout
     */
    public function testPipeWithLengthTimeout()
    {
        list($readable) = $this->createStreams();
        
        $length = 8;
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($length) {
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, strlen(self::WRITE_STRING) + 1, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
    
    /**
     * @depends testPipe
     */
    public function testPipeTo()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 10;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($offset) {
                 $this->assertSame(substr(self::WRITE_STRING, 0, $offset + 1), $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $promise = $readable->pipeTo($mock, $char);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($offset + 1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToIntegerByte()
    {
        list($readable, $writable) = $this->createStreams();
        
        $offset = 10;
        $byte = unpack('C', substr(self::WRITE_STRING, $offset, 1));
        $byte = $byte[1];
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($offset) {
                 $this->assertSame(substr(self::WRITE_STRING, 0, $offset + 1), $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $promise = $readable->pipeTo($mock, $byte);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($offset + 1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToOnUnwritableStream()
    {
        list($readable) = $this->createStreams();
        
        $offset = 5;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(false));
        
        $promise = $readable->pipeTo($mock, $char);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnwritableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToMultibyteString()
    {
        list($readable) = $this->createStreams();
        
        $offset = 5;
        $length = 3;
        $string = substr(self::WRITE_STRING, $offset, $length);
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $promise = $readable->pipeTo($mock, $string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($offset + 1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToEndOnClose()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->once())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, '!', true);
        
        $promise->done($this->createCallback(0), $this->createCallback(1));
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToDoNotEndOnClose()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, '!', false);
        
        $promise->done($this->createCallback(0), $this->createCallback(1));
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        
        $readable->close();
        
        Loop::run();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToWithLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $length = 8;
        $offset = 10;
        $char = substr(self::WRITE_STRING, $offset, 1);
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($length) {
                 $this->assertSame(substr(self::WRITE_STRING, 0, $length), $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, $char, false, $length);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($length));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::tick();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($offset, $length) {
                 $this->assertSame(substr(self::WRITE_STRING, $length, $offset - $length + 1), $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, $char, false, strlen(self::WRITE_STRING));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($offset - $length + 1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::tick();
        
        $this->assertFalse($promise->isPending());
    }
    
    /**
     * @depends testPipeToWithLength
     */
    public function testPipeToWithInvalidLength()
    {
        list($readable, $writable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->never())
             ->method('write');
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, '!', false, -1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::tick();
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToTimeout()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(self::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, '!', false, null, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
    
    /**
     * @depends testPipeToWithLength
     * @depends testPipeToTimeout
     */
    public function testPipeToWithLengthTimeout()
    {
        list($readable) = $this->createStreams();
        
        $length = 8;
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) use ($length) {
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipeTo($mock, '!', false, strlen(self::WRITE_STRING) + 1, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
}
