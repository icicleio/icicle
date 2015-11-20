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
use Icicle\Awaitable\Awaitable as AwaitableInterface;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class MapTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testEmptyArray()
    {
        $values = [];
        
        $result = Awaitable\map($this->createCallback(0), $values);
        
        $this->assertSame($result, $values);
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return $value + 1;
        };
        
        $result = Awaitable\map($callback, $values);
        
        Loop\run();
        
        $this->assertTrue(is_array($result));
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertSame($values[$key] + 1, $promise->wait());
        }
    }
    
    /**
     * @depends testValuesArray
     */
    public function testFulfilledPromisesArray()
    {
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return $value + 1;
        };
        
        $result = Awaitable\map($callback, $promises);
        
        Loop\run();
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertSame($promises[$key]->wait() + 1, $promise->wait());
        }
    }
    
    /**
     * @depends testValuesArray
     */
    public function testPendingPromisesArray()
    {
        $promises = [
            Awaitable\resolve(1)->delay(0.2),
            Awaitable\resolve(2)->delay(0.3),
            Awaitable\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return $value + 1;
        };
        
        $result = Awaitable\map($callback, $promises);
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }
        
        Loop\run();
        
        foreach ($result as $key => $promise) {
            $this->assertTrue($promise->isFulfilled());
            $this->assertSame($promises[$key]->wait() + 1, $promise->wait());
        }
    }
    
    /**
     * @depends testValuesArray
     */
    public function testRejectedPromisesArray()
    {
        $exception = new Exception();
        
        $promises = [
            Awaitable\reject($exception),
            Awaitable\reject($exception),
            Awaitable\reject($exception)
        ];
        
        $result = Awaitable\map($this->createCallback(0), $promises);
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertTrue($promise->isRejected());

            try {
                $promise->wait();
            } catch (Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        }
    }
    
    /**
     * @depends testRejectedPromisesArray
     */
    public function testCallbackThrowingExceptionRejectsPromises()
    {
        $values = [1, 2, 3];
        $exception = new Exception();
        
        $callback = $this->createCallback(3);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $result = Awaitable\map($callback, $values);
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }
        
        Loop\run();
        
        foreach ($result as $key => $promise) {
            $this->assertTrue($promise->isRejected());

            try {
                $promise->wait();
            } catch (Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        }
    }

    public function testMultipleArrays()
    {
        $values1 = [1, 2, 3];
        $values2 = [3, 2, 1];

        $callback = $this->createCallback(3);
        $callback = function ($value1, $value2) use ($callback) {
            $callback();
            return $value1 + $value2;
        };

        $result = Awaitable\map($callback, $values1, $values2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }

        Loop\run();

        foreach ($result as $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertSame(4, $promise->wait());
        }
    }

    /**
     * @depends testMultipleArrays
     */
    public function testMultipleArraysWithPromises()
    {
        $promises1 = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        $promises2 = [Awaitable\resolve(3), Awaitable\resolve(2), Awaitable\resolve(1)];

        $callback = $this->createCallback(3);
        $callback = function ($value1, $value2) use ($callback) {
            $callback();
            return $value1 + $value2;
        };

        $result = Awaitable\map($callback, $promises1, $promises2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }

        Loop\run();

        foreach ($result as $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertSame(4, $promise->wait());
        }
    }

    /**
     * @depends testMultipleArrays
     */
    public function testMultipleArraysWithPendingPromises()
    {
        $promises1 = [
            Awaitable\resolve(1)->delay(0.2),
            Awaitable\resolve(2)->delay(0.3),
            Awaitable\resolve(3)->delay(0.1)
        ];
        $promises2 = [
            Awaitable\resolve(3)->delay(0.1),
            Awaitable\resolve(2)->delay(0.2),
            Awaitable\resolve(1)->delay(0.3)
        ];

        $callback = $this->createCallback(3);
        $callback = function ($value1, $value2) use ($callback) {
            $callback();
            return $value1 + $value2;
        };

        $result = Awaitable\map($callback, $promises1, $promises2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }

        Loop\run();

        foreach ($result as $promise) {
            $this->assertInstanceOf(AwaitableInterface::class, $promise);
            $this->assertSame(4, $promise->wait());
        }
    }

    /**
     * @depends testMultipleArrays
     */
    public function testMultipleArraysWithRejectedPromises()
    {
        $exception = new Exception();

        $promises1 = [
            Awaitable\reject($exception),
            Awaitable\resolve(2),
            Awaitable\reject($exception)
        ];
        $promises2 = [
            Awaitable\resolve(3),
            Awaitable\reject($exception),
            Awaitable\reject(new Exception())
        ];

        $callback = $this->createCallback(0);

        $result = Awaitable\map($callback, $promises1, $promises2);

        Loop\run();

        foreach ($result as $key => $promise) {
            $this->assertTrue($promise->isRejected());
            try {
                $promise->wait();
            } catch (Exception $reason) {
                $this->assertSame($exception, $reason);
            }
        }
    }
}
