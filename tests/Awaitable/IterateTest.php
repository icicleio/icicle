<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Awaitable;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class IterateTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testSeedReturnedWhenPredicateImmediatelyReturnsFalse()
    {
        $seed = 1;
        
        $predicate = function ($value) use (&$parameter) {
            $parameter = $value;
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($seed));
        
        Awaitable\iterate($this->createCallback(0), $predicate, $seed)
               ->done($callback);
        
        Loop\run();
        
        $this->assertSame($seed, $parameter);
    }
    
    /**
     * @depends testSeedReturnedWhenPredicateImmediatelyReturnsFalse
     */
    public function testFulfilledPromiseAsSeed()
    {
        $seed = 1;
        $promise = Awaitable\resolve($seed);
        
        $predicate = function ($value) use (&$parameter) {
            $parameter = $value;
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($seed));
        
        Awaitable\iterate($this->createCallback(0), $predicate, $promise)
               ->done($callback);
        
        Loop\run();
        
        $this->assertSame($seed, $parameter);
    }
    
    /**
     * @depends testSeedReturnedWhenPredicateImmediatelyReturnsFalse
     */
    public function testRejectedPromiseAsSeed()
    {
        $exception = new Exception();
        $promise = Awaitable\reject($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\iterate($this->createCallback(0), $this->createCallback(0), $promise)
               ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testRejectedWhenPredicateThrowsException()
    {
        $exception = new Exception();
        
        $predicate = $this->createCallback(1);
        $predicate->method('__invoke')
                  ->will($this->throwException($exception));
        
        Awaitable\iterate($this->createCallback(0), $predicate, 1);
        
        Loop\run();
    }
    
    public function testRejectedWhenWorkerThrowsException()
    {
        $exception = new Exception();
        
        $predicate = function () {
            return true;
        };
        
        $worker = $this->createCallback(1);
        $worker->method('__invoke')
               ->will($this->throwException($exception));
        
        Awaitable\iterate($worker, $predicate, 1);
        
        Loop\run();
    }
    
    public function testWorkerReturnsFulfilledPromise()
    {
        $predicate = function () {
            static $count = 2;
            return 0 !== --$count;
        };
        
        $worker = function ($value) {
            return Awaitable\resolve($value + 1);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Awaitable\iterate($worker, $predicate, 1)
               ->done($callback);
        
        Loop\run();
    }
    
    public function testWorkerReturnsRejectedPromise()
    {
        $exception = new Exception();
        
        $predicate = function ($value) {
            static $count = 2;
            return 0 !== --$count;
        };
        
        $worker = function ($value) use ($exception) {
            return Awaitable\reject($exception);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\iterate($worker, $predicate, 1)
               ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testWorkerReturnsPendingPromise()
    {
        $predicate = function ($value) {
            static $count = 2;
            return 0 !== --$count;
        };
        
        $worker = function ($value) {
            return Awaitable\resolve($value + 1)->delay(0.1);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Awaitable\iterate($worker, $predicate, 1)
               ->done($callback);
        
        Loop\run();
    }

    /**
     * @depends testSeedReturnedWhenPredicateImmediatelyReturnsFalse
     */
    public function testVoidPredicateStopsIteration()
    {
        $seed = 1;

        $predicate = function () {};

        $worker = function ($value) {
            return $value + 1;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($seed));

        Awaitable\iterate($worker, $predicate, $seed)
               ->done($callback);

        Loop\run();
    }

    public function testInnerPromiseCancelledOnCancellation()
    {
        $delay = 0.1;

        $exception = new Exception();

        $predicate = function ($value) {
            static $count = 2;
            return 0 !== --$count;
        };

        $promise = Awaitable\resolve()->delay($delay * 2);

        $worker = function () use ($promise) {
            return $promise;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));

        $promise->done($this->createCallback(0), $callback);

        $promise = Awaitable\iterate($worker, $predicate);
        Loop\timer($delay, [$promise, 'cancel'], $exception);

        Loop\run();
    }

    /**
     * @depends testInnerPromiseCancelledOnCancellation
     */
    public function testIterationStoppedOnCancelledWithFulfilledInnerPromise()
    {
        $delay = 0.1;

        $exception = new Exception();

        $predicate = function () {
            return true;
        };

        $promise = Awaitable\resolve();

        $worker = function () use ($promise) {
            return $promise;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));

        $promise = Awaitable\iterate($worker, $predicate);
        $promise->done($this->createCallback(0), $callback);

        Loop\timer($delay, [$promise, 'cancel'], $exception);

        Loop\run();
    }
}
