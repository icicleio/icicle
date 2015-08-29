<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\UvLoop;

/**
 * @requires extension uv
 */
class UvLoopTest extends AbstractLoopTest
{
    protected static $base;

    public static function setUpBeforeClass()
    {
        if (extension_loaded('uv')) {
            self::$base = \uv_loop_new();
        }
    }

    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new UvLoop(true, $eventFactory, self::$base);
    }

    public function testEnabled()
    {
        $this->assertSame(extension_loaded('uv'), UvLoop::enabled());
    }
}
