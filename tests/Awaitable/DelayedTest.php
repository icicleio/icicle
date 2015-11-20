<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class DelayedTest extends TestCase
{
    /**
     * @var \Icicle\Awaitable\Delayed
     */
    private $delayed;

    public function setUp()
    {
        Loop\loop(new SelectLoop());

        $this->delayed = new Delayed();
    }

    public function testResolve()
    {
        $value = 1;

        $this->delayed->resolve($value);

        $this->assertFalse($this->delayed->isPending());
        $this->assertTrue($this->delayed->isFulfilled());

        $this->assertSame($value, $this->delayed->wait());
    }

    public function testReject()
    {
        $exception = new Exception();

        $this->delayed->reject($exception);

        $this->assertFalse($this->delayed->isPending());
        $this->assertTrue($this->delayed->isRejected());

        try {
            $this->delayed->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testResolve
     */
    public function testResolveWithFulfilledAwaitable()
    {
        $value = 1;

        $awaitable = Awaitable\resolve($value);

        $this->delayed->resolve($awaitable);

        $this->assertFalse($this->delayed->isPending());
        $this->assertTrue($this->delayed->isFulfilled());

        $this->assertSame($value, $this->delayed->wait());
    }

    /**
     * @depends testResolve
     */
    public function testResolveWithRejectedAwaitable()
    {
        $exception = new Exception();

        $awaitable = Awaitable\reject($exception);

        $this->delayed->resolve($awaitable);

        $this->assertFalse($this->delayed->isPending());
        $this->assertTrue($this->delayed->isRejected());

        try {
            $this->delayed->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testResolve
     */
    public function testResolveWithPendingAwaitable()
    {
        $value = 1;

        $awaitable = new Delayed();

        $this->delayed->resolve($awaitable);

        $this->assertTrue($this->delayed->isPending());

        $awaitable->resolve($value);

        $this->assertTrue($this->delayed->isFulfilled());

        $this->assertSame($value, $this->delayed->wait());
    }
}