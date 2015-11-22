<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Awaitable\Internal;

use Icicle\Awaitable\{Internal\FulfilledAwaitable, Promise};
use Icicle\Tests\TestCase;

/**
 * Tests the constructor only. All other methods are covered by PromiseTest.
 */
class FulfilledAwaitableTest extends TestCase
{
    /**
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testCannotUsePromseAsValue()
    {
        $promise = new Promise(function () {});
        
        $fulfilled = new FulfilledAwaitable($promise);
    }
}
