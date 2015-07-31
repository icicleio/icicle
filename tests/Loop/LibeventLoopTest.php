<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
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
    
    public function createLoop(EventFactoryInterface $eventFactory)
    {
        return new LibeventLoop($eventFactory, self::$base);
    }
    
    public function testEnabled()
    {
        $this->assertSame(extension_loaded('libevent'), LibeventLoop::enabled());
    }
}
