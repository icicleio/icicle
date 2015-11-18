<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Awaitable\Internal;

use Icicle\Awaitable\Promise;
use Icicle\Awaitable\Internal\FulfilledAwaitable;
use Icicle\Tests\TestCase;

/**
 * Tests the constructor only. All other methods are covered by PromiseTest.
 */
class FulfilledAwaitableTest extends TestCase
{
    /**
     * @expectedException \Icicle\Awaitable\Exception\InvalidArgumentError
     */
    public function testCannotUsePromseAsValue()
    {
        $promise = new Promise(function () {});
        
        $fulfilled = new FulfilledAwaitable($promise);
    }
}
