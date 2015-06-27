<?php
namespace Icicle\Tests\Socket\Stream;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Promise;

trait ReadableSocketTestTrait
{
    /**
     * @return \Icicle\Stream\ReadableStreamInterface[]|\Icicle\Stream\WritableStreamInterface[]
     */
    abstract public function createStreams();

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
        
        Loop\run(); // Drain readable buffer.

        $promise = $readable->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop\run(); // Should get an empty string.

        $promise = $readable->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run(); // Should reject with UnreadableException.
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

        Loop\run();

        $promise = $readable->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run(); // Should reject with UnreadableException.
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
        
        Loop\run(); // Drain readable buffer.

        $promise = $readable->read(null, "\0");

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop\run(); // Should get an empty string.

        $promise = $readable->read(null, "\0");
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        Loop\run(); // Should reject with UnreadableException.
    }
}
