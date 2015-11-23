<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Awaitable\Internal;

use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Awaitable\Internal\UncancellableAwaitable;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

/**
 * Tests the constructor only. All other methods are covered by PromiseTest.
 */
class UncancellableAwaitableTest extends TestCase
{
    const TIMEOUT = 0.1;

    /**
     * @var \Icicle\Awaitable\Delayed
     */
    private $delayed;

    /**
     * @var \Icicle\Awaitable\Internal\UncancellableAwaitable
     */
    private $uncancellable;

    public function setUp()
    {
        Loop\loop(new SelectLoop());

        $this->delayed = new Delayed();
        $this->uncancellable = new UncancellableAwaitable($this->delayed);
    }

    public function testResolution()
    {
        $this->assertTrue($this->uncancellable->isPending());
        $this->assertFalse($this->uncancellable->isFulfilled());
        $this->assertFalse($this->uncancellable->isRejected());

        $this->delayed->resolve();

        $this->assertFalse($this->uncancellable->isPending());
        $this->assertTrue($this->uncancellable->isFulfilled());
        $this->assertFalse($this->uncancellable->isRejected());
        $this->assertFalse($this->uncancellable->isCancelled());
    }

    public function testThen()
    {
        $value = 1;

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value))
            ->will($this->returnValue($value));

        $awaitable = $this->uncancellable->then($callback);

        $this->assertInstanceOf(Awaitable::class, $awaitable);

        $this->delayed->resolve($value);

        Loop\run();
    }

    public function testDone()
    {
        $value = 1;

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $this->uncancellable->done($callback);

        $this->delayed->resolve($value);

        Loop\run();
    }

    public function testTimeout()
    {
        $awaitable = $this->uncancellable->timeout(self::TIMEOUT);

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT - self::RUNTIME_PRECISION);

        $this->assertTrue($this->uncancellable->isPending());
        $this->assertTrue($awaitable->isRejected());
    }

    public function testDelay()
    {
        $awaitable = $this->uncancellable->delay(self::TIMEOUT);

        $this->delayed->resolve();

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT - self::RUNTIME_PRECISION);

        $this->assertTrue($awaitable->isFulfilled());
    }

    public function testUncancellable()
    {
        $this->assertSame($this->uncancellable, $this->uncancellable->uncancellable());
    }

    public function testWait()
    {
        $value = 1;

        $this->delayed->resolve($value);

        $this->assertSame($value, $this->uncancellable->wait());
    }
}
