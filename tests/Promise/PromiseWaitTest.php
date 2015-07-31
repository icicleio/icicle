<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Tests\TestCase;

class PromiseWaitTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testWaitOnFulfilledPromise()
    {
        $value = 'test';

        $promise = Promise\resolve($value);

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    public function testWaitOnRejectedPromise()
    {
        $exception = new Exception();

        $promise = Promise\reject($exception);

        try {
            $result = Promise\wait($promise);
            $this->fail('Rejection exception should be thrown from wait().');
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
        }
    }

    /**
     * @depends testWaitOnFulfilledPromise
     */
    public function testWaitOnPendingPromise()
    {
        $value = 'test';

        $promise = Promise\resolve('test')->delay(0.1);

        $this->assertTrue($promise->isPending());

        $result = Promise\wait($promise);

        $this->assertSame($value, $result);
    }

    /**
     * @expectedException \Icicle\Promise\Exception\UnresolvedError
     */
    public function testPromiseWithNoResolutionPathThrowsException()
    {
        $promise = new Promise\Promise(function () {});

        $result = Promise\wait($promise);
    }
}
