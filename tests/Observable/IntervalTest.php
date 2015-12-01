<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Coroutine as CoroutineNS;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Observable;
use Icicle\Tests\TestCase;

class IntervalTest extends TestCase
{
    const TIMEOUT = 0.1;

    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }

    public function testInterval()
    {
        $count = 3;
        $observable = Observable\interval(self::TIMEOUT, $count);

        $i = 0;
        $awaitable = new Coroutine($observable->each(function ($value) use (&$i) {
            $this->assertSame(++$i, $value);
        }));

        $awaitable->wait();

        $this->assertSame($count, $i);

        $this->assertTrue($observable->isComplete());
        $this->assertFalse($observable->isFailed());
    }

    /**
     * @depends testInterval
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testIntervalWithoutLimit()
    {
        $count = 5;
        $observable = Observable\interval(self::TIMEOUT);

        $i = 0;
        $awaitable = new Coroutine($observable->each(function ($value) use (&$i, $count, $observable) {
            $this->assertSame(++$i, $value);
            if ($i === $count) {
                $observable->dispose();
            }
        }));

        $awaitable->wait();
    }

    /**
     * @depends testInterval
     */
    public function testIntervalWithSlowConsumer()
    {
        $count = 5;
        $observable = Observable\interval(self::TIMEOUT, $count);

        $i = 0;
        $awaitable = new Coroutine($observable->each(function () use (&$i) {
            ++$i;
            yield CoroutineNS\sleep(self::TIMEOUT * 2);
        }));

        $awaitable->wait();

        $this->assertSame((int) ($count / 2), $i);
    }

    /**
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposedInterval()
    {
        $observable = Observable\interval(self::TIMEOUT);

        $awaitable = new Coroutine($observable->each(function ($value) use ($observable) {
            if (2 === $value) {
                $observable->dispose();
            }
        }));

        $awaitable->wait();
    }

    /**
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testInvalidCount()
    {
        $observable = Observable\interval(self::TIMEOUT, -1);

        $coroutine = new Coroutine($observable->each($this->createCallback(0)));
        $coroutine->wait();
    }
}
