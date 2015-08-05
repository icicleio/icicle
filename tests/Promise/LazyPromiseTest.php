<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Tests\TestCase;

class LazyPromiseTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testPromisorNotCalledOnConstruct()
    {
        $lazy = Promise\lazy($this->createCallback(0));
    }
    
    /**
     * @depends testPromisorNotCalledOnConstruct
     */
    public function testPromisorCalledWhenThenCalled()
    {
        $called = false;
        
        $promisor = function () use (&$called) {
            $called = true;
        };
        
        $lazy = Promise\lazy($promisor);
        
        $this->assertFalse($called);
        
        $child = $lazy->then();
        
        $this->assertTrue($called);
    }
    
    /**
     * @depends testPromisorNotCalledOnConstruct
     */
    public function testPromisorCalledWhenDoneCalled()
    {
        $called = false;
        
        $promisor = function () use (&$called) {
            $called = true;
        };
        
        $lazy = Promise\lazy($promisor);
        
        $this->assertFalse($called);
        
        $lazy->done();
        
        $this->assertTrue($called);
    }
    
    /**
     * @depends testPromisorNotCalledOnConstruct
     */
    public function testPromisorCalledWhenCancelCalled()
    {
        $called = false;
        
        $promisor = function () use (&$called) {
            $called = true;
        };
        
        $lazy = Promise\lazy($promisor);
        
        $this->assertFalse($called);
        
        $lazy->cancel();
        
        $this->assertTrue($called);
    }
    
    /**
     * @depends testPromisorNotCalledOnConstruct
     */
    public function testPromisorCalledWhenDelayCalled()
    {
        $called = false;
        
        $promisor = function () use (&$called) {
            $called = true;
        };
        
        $lazy = Promise\lazy($promisor);
        
        $this->assertFalse($called);
        
        $lazy->delay(0.1);
        
        $this->assertTrue($called);
    }
    
    /**
     * @depends testPromisorNotCalledOnConstruct
     */
    public function testPromisorCalledWhenTimeoutCalled()
    {
        $called = false;
        
        $promisor = function () use (&$called) {
            $called = true;
        };
        
        $lazy = Promise\lazy($promisor);
        
        $this->assertFalse($called);
        
        $lazy->timeout(0.1);
        
        $this->assertTrue($called);
    }
    
    /**
     * @depends testPromisorCalledWhenThenCalled
     */
    public function testPromiseRejectedIfPromisorThrowsException()
    {
        $exception = new Exception();
        
        $promisor = $this->createCallback(1);
        $promisor->method('__invoke')
                 ->will($this->throwException($exception));
        
        $lazy = Promise\lazy($promisor);
        
        $child = $lazy->then($this->createCallback(0), $this->createCallback(1));
        
        $this->assertFalse($lazy->isPending());
        $this->assertFalse($lazy->isFulfilled());
        $this->assertTrue($lazy->isRejected());
        $this->assertFalse($lazy->isCancelled());
        $this->assertSame($exception, $lazy->getResult());
        
        Loop\run();
    }

    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testConstructWithArguments()
    {
        $value = 'test';

        $promisor = function ($value) { return $value; };

        $lazy = Promise\lazy($promisor, $value);

        $this->assertFalse($lazy->isPending());
        $this->assertSame($value, $lazy->getResult());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));

        $lazy->done($callback);

        Loop\run();
    }

    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testPromisorReturnsFulfilledPromise()
    {
        $value = 'test';
        $promise = Promise\resolve($value);
        
        $promisor = function () use ($promise) { return $promise; };
        
        $lazy = Promise\lazy($promisor);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $lazy->done($callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testPromisorReturnsRejectedPromise()
    {
        $exception = new Exception();
        $promise = Promise\reject($exception);
        
        $promisor = function () use ($promise) { return $promise; };
        
        $lazy = Promise\lazy($promisor);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $lazy->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testPromisorReturnsPendingPromise()
    {
        $promise = new Promise\Promise(function () {});
        
        $promisor = function () use ($promise) { return $promise; };
        
        $lazy = Promise\lazy($promisor);
        
        $lazy->done($this->createCallback(0));
        
        $this->assertTrue($lazy->isPending());
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testResolvePromiseWithLazyPromise()
    {
        $value = 'test';
        
        $promise = new Promise\Promise(function ($resolve) use ($value) {
            $promise = Promise\resolve($value);
            $promisor = function () use ($promise) { return $promise; };
            $resolve(Promise\lazy($promisor));
        });
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $promise->done($callback);
        
        Loop\run();
    }
}
