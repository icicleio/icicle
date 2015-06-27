<?php
namespace Icicle\Tests\Socket\Stream;

use Icicle\Loop;
use Icicle\Socket\Exception\FailureException;

trait WritableSocketTestTrait
{
    /**
     * @return \Icicle\Stream\ReadableStreamInterface[]|\Icicle\Stream\WritableStreamInterface[]
     */
    abstract public function createStreams();

    /**
     * @depends testWrite
     */
    public function testWriteFailure()
    {
        list($readable, $writable) = $this->createStreams();

        // Use fclose() manually since $writable->close() will prevent behavior to be tested.
        fclose($writable->getResource());

        $promise = $writable->write(StreamTest::WRITE_STRING);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(FailureException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }
}
