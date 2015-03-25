<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\SelectLoop;

class SelectLoopTest extends AbstractLoopTest
{
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new SelectLoop($eventFactory);
    }
    
    public function testEnabled()
    {
        $this->assertTrue(SelectLoop::enabled());
    }
    
    public function testListenAwaitWithExpiredTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $length = strlen(self::WRITE_STRING);
        
        fclose($writable); // A closed socket will never be writable, but is invalid in other loop implementations.
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($writable), $this->identicalTo(true));
        
        $await = $this->loop->createAwait($writable, $callback);
        
        $await->listen(self::TIMEOUT);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
}
