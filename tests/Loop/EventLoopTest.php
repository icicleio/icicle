<?php
namespace Icicle\Tests\Loop;

use EventBase;
use EventConfig;
use Icicle\Loop\EventLoop;
use Icicle\Loop\Events\EventFactoryInterface;

/**
 * @requires extension event
 */
class EventLoopTest extends AbstractLoopTest
{
    protected static $base;
    
    public static function setUpBeforeClass()
    {
        if (extension_loaded('event')) {
            self::$base = new EventBase();
        }
    }
    
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new EventLoop($eventFactory, self::$base);
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('event'), EventLoop::enabled());
    }
}
