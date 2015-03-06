<?php
namespace Icicle\Tests\Socket;

use Icicle\Loop\Loop;

trait WritableSocketTestTrait
{
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
}
