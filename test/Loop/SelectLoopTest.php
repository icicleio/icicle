<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\SelectLoop;
use Icicle\Socket\Stream;

class SelectLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        return new SelectLoop();
    }
    
    public function testEnabled()
    {
        $this->assertTrue(SelectLoop::enabled());
    }
}
