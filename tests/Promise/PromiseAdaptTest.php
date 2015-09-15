<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Promise\Exception\{InvalidArgumentError, RejectedException};
use Icicle\Promise\PromiseInterface;
use Icicle\Tests\TestCase;

class PromiseAdaptTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testThenCalled()
    {
        $mock = $this->getMock(PromiseInterface::class);

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

        $promise = Promise\adapt($mock);

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $promise->done($this->createCallback(0));

        Loop\run();
    }

    /**
     * @depends testThenCalled
     */
    public function testThenableFulfilled()
    {
        $value = 1;

        $mock = $this->getMock(PromiseInterface::class);

        $mock->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($resolve, $reject) use ($value) {
                $resolve($value);
            }));

        $promise = Promise\adapt($mock);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $promise->done($callback);

        Loop\run();
    }

    /**
     * @depends testThenCalled
     */
    public function testThenableRejected()
    {
        $reason = 'Rejected';

        $mock = $this->getMock(PromiseInterface::class);

        $mock->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($resolve, $reject) use ($reason) {
                $reject($reason);
            }));

        $promise = Promise\adapt($mock);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(RejectedException::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testScalarValue()
    {
        $value = 1;

        $promise = Promise\adapt($value);

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(InvalidArgumentError::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testNonThenableObject()
    {
        $object = new \stdClass();

        $promise = Promise\adapt($object);

        $this->assertInstanceOf(PromiseInterface::class, $promise);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(InvalidArgumentError::class));

        $promise->done($this->createCallback(0), $callback);

        Loop\run();
    }
}
