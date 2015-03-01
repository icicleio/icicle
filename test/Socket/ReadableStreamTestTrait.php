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
        
        $promise = $readable->read(null, self::TIMEOUT);
        
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
    public function testReadAfterEof()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read();
        
        Loop::run(); // Drain readable buffer.
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\EofException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        fclose($writable->getResource()); // Close other end of pipe.
        
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
                 $this->assertSame($data, self::WRITE_STRING);
                 if (3 > $count) {
                     return Promise::resolve(strlen($data));
                 } else {
                     return Promise::reject(new Exception());
                 }
             }));
        
        $promise = $readable->pipe($mock);
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        fwrite($writable->getResource(), self::WRITE_STRING);
        
        Loop::tick();
        
        $this->assertTrue($promise->isPending());
        fwrite($writable->getResource(), self::WRITE_STRING);
        
        Loop::tick();
        
        $this->assertTrue($promise->isFulfilled());
        $this->assertSame($promise->getResult(), strlen(self::WRITE_STRING) * 3);
    }
    
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
                 $this->assertSame($data, self::WRITE_STRING);
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
                 $this->assertSame($data, self::WRITE_STRING);
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
                 $this->assertSame($data, self::WRITE_STRING);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->once())
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
    public function testPipeTimeout()
    {
        list($readable) = $this->createStreams();
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame($data, self::WRITE_STRING);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, self::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
}
