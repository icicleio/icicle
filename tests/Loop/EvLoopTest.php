<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\EvLoop;
use Icicle\Loop\Events\EventFactoryInterface;

/**
 * @requires extension ev
 */
class EvLoopTest extends AbstractLoopTest
{
    protected static $base;

    public static function setUpBeforeClass()
    {
        if (extension_loaded('ev')) {
            self::$base = new \EvLoop();
        }
    }

    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new EvLoop(true, $eventFactory, self::$base);
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('ev'), EvLoop::enabled());
    }
}
