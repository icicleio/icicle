<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop\Loop;

trait WritableBufferedStreamTestTrait
{
    /**
     * @depends testWrite
     */
    public function testCloseAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $writable->close();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\ClosedException'));
        
        $promise->done($this->createCallback(0), $callback);
        
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
        
        for ($i = 0; $i < self::CHUNK_SIZE + 1; ++$i) {
            $buffer .= '1';
        }
        
        $promise = $writable->write($buffer);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($buffer)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $this->assertTrue($promise->isPending());
        
        while ($promise->isPending()) {
            $readable->read(); // Pull more data out of the buffer.
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
        
        while ($promise->isPending()) {
            $readable->read(); // Pull more data out of the buffer.
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
        
        while ($promise->isPending()) {
            $readable->read(); // Pull more data out of the buffer.
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
        
        $readable->close(); // Close other end of pipe.
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Socket\Exception\FailureException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testAwait
     */
    public function testAwaitAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(self::WRITE_STRING);
        } while (!$promise->isPending());
        
        $promise = $writable->await();
        
        $promise->done($this->createCallback(1), $this->createCallback(0));
        
        while ($promise->isPending()) {
            $readable->read(); // Pull more data out of the buffer.
            Loop::tick();
        }
    }
}
