<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\EvLoop;

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

    public function tearDown()
    {
        $this->loop->clear();
    }

    public function createLoop()
    {
        return new EvLoop(true, self::$base);
    }
}
