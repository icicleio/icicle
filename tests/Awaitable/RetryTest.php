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
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class RetryTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testPromisorReturningScalar()
    {
        $value = 'testing';
        
        $promisor = function () use ($value) {
            return Awaitable\resolve($value);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Awaitable\retry($promisor, $this->createCallback(0))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisorReturnsFulfilledPromise()
    {
        $value = 'testing';
        
        $promisor = function () use ($value) {
            return Awaitable\resolve($value);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Awaitable\retry($promisor, $this->createCallback(0))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisorReturnsPendingPromise()
    {
        $value = 'testing';
        
        $promisor = function () use ($value) {
            return Awaitable\resolve($value)->delay(self::TIMEOUT);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Awaitable\retry($promisor, $this->createCallback(0))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPromisorThrowsException()
    {
        $exception = new Exception();
        
        $promisor = function () use ($exception) {
            throw $exception;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\retry($promisor, $this->createCallback(0))
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testPromiseRejectingCallsOnRejected()
    {
        $exception = new Exception();
        
        $promisor = function () use ($exception) {
            return Awaitable\reject($exception);
        };
        
        $onRejected = function ($value) use ($exception) {
            $this->assertSame($exception, $value);
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testPendingPromiseRejectingCallsOnRejected()
    {
        $exception = new Exception();
        
        $promisor = function () use ($exception) {
            $promise = new Awaitable\Promise(function () {});
            return $promise->timeout(self::TIMEOUT, function () use ($exception) {
                throw $exception;
            });
        };
        
        $onRejected = function ($value) use ($exception) {
            $this->assertSame($exception, $value);
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testOnRejectedThrowingRejectsPromise()
    {
        $exception = new Exception();
        
        $promisor = function () {
            return Awaitable\reject(new Exception());
        };
        
        $onRejected = function () use ($exception) {
            throw $exception;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testOnRejectedReturningTrueCallsPromisor()
    {
        $value = 'testing';
        $exception = new Exception();
        
        $promisor = function () use ($value, $exception) {
            static $initial = true;
            if ($initial) {
                $initial = false;
                return Awaitable\reject($exception);
            }
            
            return Awaitable\resolve($value);
        };
        
        $onRejected = function ($value) use ($exception) {
            $this->assertSame($exception, $value);
            return true;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        Awaitable\retry($promisor, $onRejected)
            ->done($callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testPromisorThrowingOnSubsequentCallRejectsPromise()
    {
        $exception1 = new Exception();
        $exception2 = new Exception();
        
        $promisor = function () use ($exception1, $exception2) {
            static $initial = true;
            if ($initial) {
                $initial = false;
                return Awaitable\reject($exception1);
            }
            
            throw $exception2;
        };
        
        $onRejected = function ($value) use ($exception1) {
            $this->assertSame($exception1, $value);
            return true;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception2));
        
        Awaitable\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }

    /**
     * @depends testPromiseRejectingCallsOnRejected
     */
    public function testVoidCallbackDoesNotCauseRetry()
    {
        $exception = new Exception();

        $promisor = function () use ($exception) {
            return Awaitable\reject($exception);
        };

        $onRejected = function () {};

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        Awaitable\retry($promisor, $onRejected)
            ->done($this->createCallback(0), $callback);

        Loop\run();
    }

    public function testInitialPromiseCancelledOnCancellation()
    {
        $delay = 0.1;

        $exception = new Exception();

        $promise = Awaitable\resolve()->delay($delay * 2);

        $promisor = function () use ($promise) {
            return $promise;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $promise->done($this->createCallback(0), $callback);

        $promise = Awaitable\retry($promisor, $this->createCallback(0));
        Loop\timer($delay, [$promise, 'cancel'], $exception);

        Loop\run();
    }

    /**
     * @depends testInitialPromiseCancelledOnCancellation
     */
    public function testPromiseCancelledOnCancellationAfterRejection()
    {
        $delay = 0.1;

        $exception = new Exception();
        $reason = new Exception();

        $promise = Awaitable\resolve()->delay($delay * 2);

        $promisor = function () use ($promise, $reason) {
            static $initial = true;
            if ($initial) {
                $initial = false;
                return Awaitable\reject($reason);
            } else {
                return $promise;
            }
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($reason));

        $onRejected = function (Exception $exception) use ($callback) {
            $callback($exception);
            return true;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $promise->done($this->createCallback(0), $callback);

        $promise = Awaitable\retry($promisor, $onRejected);
        Loop\timer($delay, [$promise, 'cancel'], $exception);

        Loop\run();
    }
}
