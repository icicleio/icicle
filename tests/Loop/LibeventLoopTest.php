<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\LibeventLoop;
use Icicle\Socket\Stream;

/**
 * @requires extension libevent
 */
class LibeventLoopTest extends AbstractLoopTest
{
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new LibeventLoop($eventFactory);
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('libevent'), LibeventLoop::enabled());
    }
}
