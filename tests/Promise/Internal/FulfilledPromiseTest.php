<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise\Internal;

use Icicle\Promise\Promise;
use Icicle\Promise\Internal\FulfilledPromise;
use Icicle\Tests\TestCase;

/**
 * Tests the constructor only. All other methods are covered by PromiseTest.
 *
 * @requires PHP 5.4
 */
class FulfilledPromiseTest extends TestCase
{
    /**
     * @expectedException \Icicle\Promise\Exception\InvalidArgumentError
     */
    public function testCannotUsePromseAsValue()
    {
        $promise = new Promise(function () {});
        
        $fulfilled = new FulfilledPromise($promise);
    }
}
