<?php
namespace Icicle\Tests\Socket;

use Icicle\Loop\Loop;

trait WritableStreamTestTrait
{
    /**
     * @return  [ReadableStreamInterface, WritableStreamInterface]
     */
    abstract public function createStreams();

    public function testWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        $data = fread($readable->getResource(), self::CHUNK_SIZE);
        
        $string = "{'New String\0To Write'}\r\n";
        
        $promise = $writable->write($string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $data = fread($readable->getResource(), self::CHUNK_SIZE);
        
        $this->assertSame($string, $data);
        
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
        
        $promise = $writable->write(self::WRITE_STRING);
        
        $writable->close();
        
        $this->assertFalse($writable->isWritable());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(self::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));
        
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
    public function testWriteTimeout()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING, self::TIMEOUT);
        } while (!$promise->isPending());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\TimeoutException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteEmptyString()
    {
        list($readable, $writable) = $this->createStreams();
        
        $data = fread($readable->getResource(), self::CHUNK_SIZE);
        
        $promise = $writable->write('');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $promise = $writable->write('0');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $data = fread($readable->getResource(), 1);
        
        $this->assertSame('0', $data);
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
        
        $this->assertTrue($writable->isOpen());
        
        Loop::run();
        
        $data = fread($readable->getResource(), self::CHUNK_SIZE);
        
        $this->assertSame(self::WRITE_STRING . self::WRITE_STRING, $data);
        
        $this->assertFalse($writable->isOpen());
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
        
        for ($i = 0; $i < self::CHUNK_SIZE + 1; ++$i) {
            $buffer .= '1';
        }
        
        $promise = $writable->write($buffer);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($buffer)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $this->assertTrue($promise->isPending());
        
        stream_set_blocking($readable->getResource(), 0);
        
        while ($promise->isPending()) {
            $data = fread($readable->getResource(), self::CHUNK_SIZE); // Pull more data out of the buffer.
            Loop::tick();
        }
    }
    
    /**
     * @depends testEnd
     * @depends testWriteAfterPendingWrite
     */
    public function testEndAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $promise = $writable->end(self::WRITE_STRING);
        
        $this->assertFalse($writable->isWritable());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(self::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $this->assertTrue($promise->isPending());
        
        stream_set_blocking($readable->getResource(), 0);
        
        while ($promise->isPending()) {
            $data = fread($readable->getResource(), self::CHUNK_SIZE); // Pull more data out of the buffer.
            Loop::tick();
        }
        
        $this->assertFalse($writable->isOpen());
    }
    
    /**
     * @depends testWriteEmptyString
     * @depends testWriteAfterPendingWrite
     */
    public function testWriteEmptyStringAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $promise = $writable->write('');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        $this->assertTrue($promise->isPending());
        
        stream_set_blocking($readable->getResource(), 0);
        
        while ($promise->isPending()) {
            $data = fread($readable->getResource(), self::CHUNK_SIZE); // Pull more data out of the buffer.
            Loop::tick();
        }
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteAfterPendingWriteAfterEof()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        // Extra write to ensure queue is not empty when write callback is called.
        $promise = $writable->write(self::WRITE_STRING);
        
        fclose($readable->getResource()); // Close other end of pipe.
        
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
        
        stream_set_blocking($readable->getResource(), 0);
        
        while ($promise->isPending()) {
            $data = fread($readable->getResource(), self::CHUNK_SIZE); // Pull more data out of the buffer.
            Loop::tick();
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
