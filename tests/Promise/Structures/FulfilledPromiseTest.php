<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise\Structures;

use Icicle\Promise\Promise;
use Icicle\Promise\Structures\FulfilledPromise;
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
