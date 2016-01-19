<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Coroutine\Coroutine;
use Icicle\Observable;
use Icicle\Tests\TestCase;

class RangeTest extends TestCase
{
    public function testBasicRange()
    {
        $start = 0;
        $end = 10;

        $observable = Observable\range($start, $end);

        $callback = $this->createCallback($end - $start + 1);
        $callback->method('__invoke')
            ->will($this->returnCallback(function ($value) use (&$start) {
                $this->assertSame($start++, $value);
            }));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertNull($awaitable->wait());
    }

    /**
     * @depends testBasicRange
     */
    public function testStep()
    {
        $start = 0;
        $end = 100;
        $step = 11;

        $observable = Observable\range($start, $end, $step);

        $callback = $this->createCallback((int) (($end - $start) / $step + 1));
        $callback->method('__invoke')
            ->will($this->returnCallback(function ($value) use (&$start, $step) {
                $this->assertSame($start, $value);
                $start += $step;
            }));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertNull($awaitable->wait());
    }

    /**
     * @depends testStep
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testInvalidStep()
    {
        $observable = Observable\range(0, 1, -1);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));
        $awaitable->wait();
    }

    /**
     * @depends testStep
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testZeroStep()
    {
        $observable = Observable\range(0, 1, 0);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));
        $awaitable->wait();
    }
}
