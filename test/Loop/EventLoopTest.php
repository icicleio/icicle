<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\EventLoop;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Socket\Stream;

/**
 * @requires extension event
 */
class EventLoopTest extends AbstractLoopTest
{
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new EventLoop($eventFactory);
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('event'), EventLoop::enabled());
    }
}
