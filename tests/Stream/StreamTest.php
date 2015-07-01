<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop;
use Icicle\Stream\Exception\ClosedException;
use Icicle\Stream\Stream;
use Icicle\Tests\TestCase;

class StreamTest extends TestCase
{
    use ReadableStreamTestTrait, WritableStreamTestTrait, WritableBufferedStreamTestTrait;

    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';
    const CHUNK_SIZE = 8192;
    const TIMEOUT = 0.1;
    const HWM = 16384;

    /**
     * @param int|null $hwm
     *
     * @return \Icicle\Stream\Stream[] Same stream instance for readable and writable.
     */
    public function createStreams($hwm = self::CHUNK_SIZE)
    {
        $stream = new Stream($hwm);

        return [$stream, $stream];
    }

    public function tearDown()
    {
        Loop\clear();
    }

    public function testEndWithPendingRead()
    {
        list($readable, $writable) = $this->createStreams();

        $promise = $readable->read();

        $this->assertTrue($promise->isPending());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(StreamTest::WRITE_STRING));

        $promise->done($callback, $this->createCallback(0));

        $promise = $writable->end(StreamTest::WRITE_STRING);

        $this->assertFalse($writable->isWritable());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(strlen(StreamTest::WRITE_STRING)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($readable->isReadable());
    }

    /**
     * @depends testEndWithPendingRead
     */
    public function testEndWithPendingReadWritingNoData()
    {
        list($readable, $writable) = $this->createStreams();

        $promise = $readable->read();

        $this->assertTrue($promise->isPending());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(ClosedException::class));

        $promise->done($this->createCallback(0), $callback);

        $promise = $writable->end();

        $this->assertFalse($writable->isWritable());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(0));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($readable->isReadable());
    }
}