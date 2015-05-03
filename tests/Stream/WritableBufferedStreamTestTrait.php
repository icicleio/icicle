<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop\Loop;

trait WritableBufferedStreamTestTrait
{
    /**
     * @return  \Icicle\Stream\Stream[]
     */
    abstract public function createStreams();

    /**
     * @depends testWrite
     */
    public function testCloseAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams(StreamTest::HWM);
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(StreamTest::WRITE_STRING);
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
        list($readable, $writable) = $this->createStreams(StreamTest::HWM);
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(StreamTest::WRITE_STRING);
        } while (!$promise->isPending());
        
        $buffer = '';
        
        for ($i = 0; $i < StreamTest::CHUNK_SIZE + 1; ++$i) {
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
        list($readable, $writable) = $this->createStreams(StreamTest::HWM);
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(StreamTest::WRITE_STRING);
        } while (!$promise->isPending());
        
        $promise = $writable->end(StreamTest::WRITE_STRING);
        
        $this->assertFalse($writable->isWritable());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(StreamTest::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));
        
        $this->assertTrue($promise->isPending());

        while ($promise->isPending()) {
            $readable->read(StreamTest::CHUNK_SIZE); // Pull more data out of the buffer.
            Loop::tick();
        }
        
        $this->assertFalse($writable->isWritable());
    }
    
    /**
     * @depends testWriteEmptyString
     * @depends testWriteAfterPendingWrite
     */
    public function testWriteEmptyStringAfterPendingWrite()
    {
        list($readable, $writable) = $this->createStreams(StreamTest::HWM);
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(StreamTest::WRITE_STRING);
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
        list($readable, $writable) = $this->createStreams(StreamTest::HWM);
        
        do { // Write until a pending promise is returned.
            $promise = $writable->write(StreamTest::WRITE_STRING);
        } while (!$promise->isPending());
        
        // Extra write to ensure queue is not empty when write callback is called.
        $promise = $writable->write(StreamTest::WRITE_STRING);
        
        $readable->close(); // Close readable stream.
        
        $promise->done($this->createCallback(0), $this->createCallback(1));
        
        Loop::run();
    }
}
