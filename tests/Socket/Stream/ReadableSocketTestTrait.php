<?php
namespace Icicle\Tests\Socket\Stream;

use Icicle\Loop\Loop;
use Icicle\Promise\Promise;

trait ReadableSocketTestTrait
{
    /**
     * @depends testRead
     */
    public function testReadAfterEof()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->write(StreamTest::WRITE_STRING);
        
        fclose($writable->getResource()); // Close other end of pipe.
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(StreamTest::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run(); // Drain readable buffer.

        $promise = $readable->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop::run(); // Should get an empty string.

        $promise = $readable->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run(); // Should reject with UnreadableException.
    }

    /**
     * @depends testRead
     */
    public function testPendingReadThenEof()
    {
        list($readable, $writable) = $this->createStreams();

        $promise = $readable->read();

        fclose($writable->getResource()); // Close other end of pipe.

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();

        $promise = $readable->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run(); // Should reject with UnreadableException.
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToAfterEof()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->write(StreamTest::WRITE_STRING);
        
        fclose($writable->getResource()); // Close other end of pipe.
        
        $promise = $readable->read(null, "\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(StreamTest::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run(); // Drain readable buffer.

        $promise = $readable->read(null, "\0");

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop::run(); // Should get an empty string.

        $promise = $readable->read(null, "\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run(); // Should reject with UnreadableException.
    }
    
    /**
     * @depends testRead
     */
    public function testReadWithTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read(null, null, StreamTest::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testReadTo
     */
    public function testReadToWithTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $readable->read(null, "\0", StreamTest::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }

    /**
     * @depends testPipe
     */
    public function testPipeTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->write(StreamTest::WRITE_STRING);
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(StreamTest::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, null, null, StreamTest::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
    
    /**
     * @depends testPipeTimeout
     */
    public function testPipeWithLengthTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->write(StreamTest::WRITE_STRING);
        
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
        
        $promise = $readable->pipe($mock, false, strlen(StreamTest::WRITE_STRING) + 1, null, StreamTest::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
    
    /**
     * @depends testPipeTo
     */
    public function testPipeToTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->write(StreamTest::WRITE_STRING);
        
        $mock = $this->getMockBuilder('Icicle\Stream\WritableStreamInterface')->getMock();
        
        $mock->method('isWritable')
             ->will($this->returnValue(true));
        
        $mock->expects($this->once())
             ->method('write')
             ->will($this->returnCallback(function ($data) {
                 $this->assertSame(StreamTest::WRITE_STRING, $data);
                 return Promise::resolve(strlen($data));
             }));
        
        $mock->expects($this->never())
             ->method('end');
        
        $promise = $readable->pipe($mock, false, null, '!', StreamTest::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
    
    /**
     * @depends testPipeToTimeout
     */
    public function testPipeToWithLengthTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->write(StreamTest::WRITE_STRING);
        
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
        
        $promise = $readable->pipe($mock, false, strlen(StreamTest::WRITE_STRING) + 1, '!', StreamTest::TIMEOUT);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertTrue($readable->isOpen());
    }
}
