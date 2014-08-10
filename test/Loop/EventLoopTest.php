<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\EventLoop;
use Icicle\Socket\Stream;

/**
 * @requires extension event
 */
class EventLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        return new EventLoop();
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('event'), EventLoop::enabled());
    }
}
