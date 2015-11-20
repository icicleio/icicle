<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
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

    public function tearDown()
    {
        $this->loop->clear();
    }

    public function createLoop()
    {
        return new UvLoop(true, self::$base);
    }
}
