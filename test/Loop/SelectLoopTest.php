<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\SelectLoop;
use Icicle\Socket\Stream;

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
}
