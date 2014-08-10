<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\LibeventLoop;
use Icicle\Socket\Stream;

/**
 * @requires extension libevent
 */
class LibeventLoopTest extends AbstractLoopTest
{
    public function createLoop()
    {
        return new LibeventLoop();
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('libevent'), LibeventLoop::enabled());
    }
}
