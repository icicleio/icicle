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
    protected static $base;
    
    public static function setUpBeforeClass()
    {
        if (extension_loaded('libevent')) {
            self::$base = event_base_new();
        }
    }
    
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new LibeventLoop($eventFactory, self::$base);
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('libevent'), LibeventLoop::enabled());
    }
}
