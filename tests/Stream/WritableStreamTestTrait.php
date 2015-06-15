<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop;

trait WritableStreamTestTrait
{
    /**
     * @return \Icicle\Stream\ReadableStreamInterface[]|\Icicle\Stream\WritableStreamInterface[]
     */
    abstract public function createStreams();

    public function testWrite()
    {
        list($readable, $writable) = $this->createStreams();
        
        $string = "{'New String\0To Write'}\r\n";
        
        $promise = $writable->write($string);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen($string)));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop\run();
        
        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($string));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteAfterClose()
    {
        list($readable, $writable) = $this->createStreams();
        
        $writable->close();
        
        $this->assertFalse($writable->isWritable());
        
        $promise = $writable->write(StreamTest::WRITE_STRING);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnwritableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testWrite
     */
    public function testWriteEmptyString()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->write('');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(0));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop\run();
        
        $promise = $writable->write('0');
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $promise->done($callback, $this->createCallback(0));
        
        $promise = $readable->read(1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo('0'));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    /**
     * @depends testWrite
     */
    public function testEnd()
    {
        list($readable, $writable) = $this->createStreams();
        
        $promise = $writable->end(StreamTest::WRITE_STRING);
        
        $this->assertFalse($writable->isWritable());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(strlen(StreamTest::WRITE_STRING)));
        
        $promise->done($callback, $this->createCallback(0));

        $this->assertTrue($writable->isOpen());

        $promise = $readable->read();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(StreamTest::WRITE_STRING));
        
        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($writable->isWritable());
        $this->assertFalse($writable->isOpen());
    }
}
