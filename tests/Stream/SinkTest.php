<?php
namespace Icicle\Tests\Stream;

use Icicle\Loop;
use Icicle\Stream\Sink;
use Icicle\Tests\TestCase;

class SinkTest extends TestCase
{
    public function tearDown()
    {
        Loop\clear();
    }
    
    /**
     * @return \Icicle\Stream\Sink
     */
    public function createSink()
    {
        return new Sink();
    }

    public function testEmptySinkIsUnreadable()
    {
        $sink = $this->createSink();

        $this->assertFalse($sink->isReadable());
        $this->assertSame(0, $sink->getLength());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnreadableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testEmptySinkIsWritable()
    {
        $sink = $this->createSink();

        $this->assertTrue($sink->isWritable());

        $promise = $sink->write(StreamTest::WRITE_STRING);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(strlen(StreamTest::WRITE_STRING)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertSame(strlen(StreamTest::WRITE_STRING), $sink->getLength());

        return $sink;
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testWriteThenSeekThenRead($sink)
    {
        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(0, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(StreamTest::WRITE_STRING));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testReadWithLength($sink)
    {
        $length = 10;

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->read($length);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(substr(StreamTest::WRITE_STRING, 0, $length)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertTrue($sink->isReadable());
        $this->assertSame($length, $sink->tell());
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testReadWithLengthLongerThanSinkLength($sink)
    {
        $length = StreamTest::CHUNK_SIZE;

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->read($length);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(StreamTest::WRITE_STRING));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
        $this->assertSame(strlen(StreamTest::WRITE_STRING), $sink->tell());
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testReadWithZeroLength($sink)
    {
        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->read(0);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertTrue($sink->isReadable());
        $this->assertSame(0, $sink->tell());
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testReadWithInvalidLength($sink)
    {
        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->read(-1);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(''));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertTrue($sink->isReadable());
        $this->assertSame(0, $sink->tell());
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testReadTo($sink)
    {
        $position = 10;
        $byte = substr(StreamTest::WRITE_STRING, $position, 1);

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->read(null, $byte);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(substr(StreamTest::WRITE_STRING, 0, $position + 1)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertTrue($sink->isReadable());
        $this->assertSame($position + 1, $sink->tell());
    }

    /**
     * @depends testEmptySinkIsWritable
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testSeekThenWrite($sink)
    {
        $promise = $sink->seek(0);

        Loop\run();

        $promise = $sink->write(StreamTest::WRITE_STRING);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(strlen(StreamTest::WRITE_STRING)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(0, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(StreamTest::WRITE_STRING . StreamTest::WRITE_STRING));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();
    }

    /**
     * @depends testWriteThenSeekThenRead
     */
    public function testWriteThenWrite()
    {
        $string = "{'New String\0To Write'}\r\n";

        $sink = $this->createSink();

        $sink->write(StreamTest::WRITE_STRING);

        Loop\run();

        $promise = $sink->write($string);

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(0, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(StreamTest::WRITE_STRING . $string));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
    }

    public function testWriteEmptyString()
    {
        $sink = $this->createSink();

        $promise = $sink->write('');

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(0));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertFalse($sink->isReadable());
        $this->assertSame(0, $sink->getLength());
    }

    /**
     * @depends testSeekThenWrite
     */
    public function testSeekToPositionThenRead()
    {
        $position = 10;

        $sink = $this->createSink();

        $sink->write(StreamTest::WRITE_STRING);

        Loop\run();

        $promise = $sink->seek($position);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame($position, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(substr(StreamTest::WRITE_STRING, $position)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
    }

    /**
     * @depends testSeekThenWrite
     */
    public function testSeekToPositionThenWrite()
    {
        $position = 10;

        $sink = $this->createSink();

        $sink->write(StreamTest::WRITE_STRING);

        Loop\run();

        $promise = $sink->seek($position);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame($position, $sink->tell());

        $promise = $sink->write(StreamTest::WRITE_STRING);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(strlen(StreamTest::WRITE_STRING)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertSame($position + strlen(StreamTest::WRITE_STRING), $sink->tell());

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(0, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(
                  substr(StreamTest::WRITE_STRING, 0 , $position)
                . StreamTest::WRITE_STRING
                . substr(StreamTest::WRITE_STRING, $position)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
    }

    /**
     * @depends testWriteThenSeekThenRead
     */
    public function testSeekFromCurrentPosition()
    {
        $position = 5;

        $sink = $this->createSink();

        $sink->write(StreamTest::WRITE_STRING);

        Loop\run();

        $promise = $sink->seek(-$position, SEEK_CUR);

        $this->assertFalse($promise->isPending());
        $this->assertSame(strlen(StreamTest::WRITE_STRING) - $position, $sink->tell());

        Loop\run();

        $promise = $sink->seek(-$position, SEEK_CUR);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(strlen(StreamTest::WRITE_STRING) - $position * 2, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(substr(StreamTest::WRITE_STRING, -($position * 2))));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
    }

    public function testSeekFromEnd()
    {
        $position = 10;

        $sink = $this->createSink();

        $sink->write(StreamTest::WRITE_STRING);

        Loop\run();

        $promise = $sink->seek(-$position, SEEK_END);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(strlen(StreamTest::WRITE_STRING) - $position, $sink->tell());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(substr(StreamTest::WRITE_STRING, -$position)));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());
    }

    public function testSeekWithInvalidOffset()
    {
        $sink = $this->createSink();

        $sink->write(StreamTest::WRITE_STRING);

        Loop\run();

        $promise = $sink->seek(-1);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\OutOfBoundsException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();

        $promise = $sink->seek($sink->getLength());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\OutOfBoundsException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();

        return $sink;
    }

    /**
     * @depends testSeekWithInvalidOffset
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testSeekWithInvalidWhence($sink)
    {
        $promise = $sink->seek(0, -1);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\InvalidArgumentException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testSeekWithInvalidOffset
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testSeekOnClosedSink($sink)
    {
        $sink->close();

        $promise = $sink->seek(0);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnseekableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testWriteThenSeekThenRead
     */
    public function testEnd()
    {
        $sink = $this->createSink();

        $promise = $sink->end(StreamTest::WRITE_STRING);

        $this->assertFalse($sink->isWritable());

        Loop\run();

        $this->assertFalse($promise->isPending());

        $promise = $sink->seek(0);

        Loop\run();

        $this->assertFalse($promise->isPending());
        $this->assertSame(0, $sink->tell());
        $this->assertTrue($sink->isReadable());

        $promise = $sink->read();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(StreamTest::WRITE_STRING));

        $promise->done($callback, $this->createCallback(0));

        Loop\run();

        $this->assertFalse($sink->isReadable());

        return $sink;
    }

    /**
     * @depends testEnd
     *
     * @param \Icicle\Stream\Sink $sink
     */
    public function testWriteToEnded($sink)
    {
        $promise = $sink->write(StreamTest::WRITE_STRING);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Stream\Exception\UnwritableException'));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }
}
