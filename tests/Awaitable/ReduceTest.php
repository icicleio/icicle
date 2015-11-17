<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Awaitable;
use Icicle\Tests\TestCase;

class ReduceTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testEmptyArrayWithNoInitial()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(null));
        
        Awaitable\reduce([], $this->createCallback(0))
               ->done($callback);
        
        Loop\run();
    }
    
    public function testEmptyArrayWithInitial()
    {
        $initial = 1;
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($initial));
        
        Awaitable\reduce([], $this->createCallback(0), $initial)
               ->done($callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Awaitable\reduce($values, function ($carry, $value) { return $carry + $value; }, 0)
               ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisesArray()
    {
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Awaitable\reduce($promises, function ($carry, $value) { return $carry + $value; }, 0)
               ->done($callback);
        
        Loop\run();
    }
    
    public function testPendingPromisesArray()
    {
        $promises = [
            Awaitable\resolve(1)->delay(0.2),
            Awaitable\resolve(2)->delay(0.3),
            Awaitable\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Awaitable\reduce($promises, function ($carry, $value) { return $carry + $value; }, 0)
               ->done($callback);
        
        Loop\run();
    }
    
    public function testFulfilledPromiseAsInitial()
    {
        $values = [1, 2, 3];
        $initial = Awaitable\resolve(4);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(10));
        
        Awaitable\reduce($values, function ($carry, $value) { return $carry + $value; }, $initial)
               ->done($callback);
        
        Loop\run();
    }
    
    public function testRejectedPromiseAsInitial()
    {
        $exception = new Exception();
        $values = [1, 2, 3];
        $initial = Awaitable\reject($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\reduce($values, function ($carry, $value) { return $carry + $value; }, $initial)
               ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testRejectOnFirstRejected()
    {
        $exception = new Exception();
        $promises = [Awaitable\resolve(1), Awaitable\reject($exception), Awaitable\resolve(3)];
        
        $mapper = function ($value) { return $value; };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\reduce($promises, function() {}, 0)
               ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testCallbackReturnsFulfilledPromise()
    {
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Awaitable\reduce(
            $promises,
            function ($carry, $value) {
                return Awaitable\resolve($carry + $value);
            },
            0
        )->done($callback);
        
        Loop\run();
    }
    
    public function testCallbackReturnsRejectedPromise()
    {
        $exception = new Exception();
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\reduce(
            $promises,
            function () use ($exception) {
                return Awaitable\reject($exception);
            },
            0
        )->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testCallbackThrowsException()
    {
        $exception = new Exception();
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\reduce(
            $promises,
            function ($carry, $value) use ($exception) {
                throw $exception;
            },
            0
        )->done($this->createCallback(0), $callback);
        
        Loop\run();
    }

    public function testCancelReduce()
    {
        $exception = new Exception();
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2)];

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $promise = Awaitable\reduce(
            $promises,
            function ($carry, $value) use ($exception) {
                return $carry + $value;
            },
            new Awaitable\Promise(function () use ($callback) { return $callback; })
        );

        $promise->done($this->createCallback(0), $callback);

        $promise->cancel($exception);

        Loop\run();
    }}
