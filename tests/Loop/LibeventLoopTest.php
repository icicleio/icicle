<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Loop\LibeventLoop;

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

    public function tearDown()
    {
        $this->loop->clear();
    }
    
    public function createLoop()
    {
        return new LibeventLoop(true, self::$base);
    }
}
