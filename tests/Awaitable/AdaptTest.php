<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Icicle\Awaitable;
use Icicle\Awaitable\{Awaitable as AwaitableInterface, Exception\RejectedException};
use Icicle\Exception\InvalidArgumentError;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class AdaptTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testThenCalled()
    {
        $mock = $this->getMock(AwaitableInterface::class);

        $mock->expects($this->once())
            ->method('then')
            ->with(
                $this->callback(function ($resolve) {
                    return is_callable($resolve);
                }),
                $this->callback(function ($reject) {
                    return is_callable($reject);
                })
            );

        $promise = Awaitable\adapt($mock);

        $this->assertInstanceOf(AwaitableInterface::class, $promise);

        $promise->done($this->createCallback(0));

        Loop\run();
    }

    /**
     * @depends testThenCalled
     */
    public function testAwaitableFulfilled()
    {
        $value = 1;

        $mock = $this->getMock(AwaitableInterface::class);

        $mock->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($resolve, $reject) use ($value) {
                $resolve($value);
                return $this->getMock(AwaitableInterface::class);
            }));

        $promise = Awaitable\adapt($mock);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $promise->done($callback);

        Loop\run();
    }

    /**
     * @depends testThenCalled
     */
    public function testAwaitableRejected()
    {
        $reason = new \Exception();

        $mock = $this->getMock(AwaitableInterface::class);

        $mock->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($resolve, $reject) use ($reason) {
                $reject($reason);
                return $this->getMock(AwaitableInterface::class);
            }));

        $promise = Awaitable\adapt($mock);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($reason));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testScalarValue()
    {
        $value = 1;

        $promise = Awaitable\adapt($value);

        $this->assertInstanceOf(AwaitableInterface::class, $promise);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(InvalidArgumentError::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testNonAwaitableObject()
    {
        $object = new \stdClass();

        $promise = Awaitable\adapt($object);

        $this->assertInstanceOf(AwaitableInterface::class, $promise);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(InvalidArgumentError::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }
}
