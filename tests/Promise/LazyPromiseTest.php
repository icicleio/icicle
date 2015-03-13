<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\LazyPromise;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class LazyPromiseTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testPromisorNotCalledOnConstruct()
    {
        $lazy = new LazyPromise($this->createCallback(0));
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
        
        $lazy = new LazyPromise($promisor);
        
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
        
        $lazy = new LazyPromise($promisor);
        
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
        
        $lazy = new LazyPromise($promisor);
        
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
        
        $lazy = new LazyPromise($promisor);
        
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
        
        $lazy = new LazyPromise($promisor);
        
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
        
        $lazy = new LazyPromise($promisor);
        
        $child = $lazy->then($this->createCallback(0), $this->createCallback(1));
        
        $this->assertFalse($lazy->isPending());
        $this->assertFalse($lazy->isFulfilled());
        $this->assertTrue($lazy->isRejected());
        $this->assertSame($exception, $lazy->getResult());
        
        Loop::run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testCallWithoutArguments()
    {
        $value = 'test';
        
        $promisor = function () use ($value) { return $value; };
        
        $lazy = LazyPromise::call($promisor);
        
        $this->assertInstanceOf('Icicle\Promise\LazyPromise', $lazy);
        $this->assertFalse($lazy->isPending());
        $this->assertSame($value, $lazy->getResult());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $lazy->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testCallWithArguments()
    {
        $value = 'test';
        
        $promisor = function ($value) { return $value; };
        
        $lazy = LazyPromise::call($promisor, $value);
        
        $this->assertInstanceOf('Icicle\Promise\LazyPromise', $lazy);
        $this->assertFalse($lazy->isPending());
        $this->assertSame($value, $lazy->getResult());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $lazy->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testPromisorReturnsFulfilledPromise()
    {
        $value = 'test';
        $promise = Promise::resolve($value);
        
        $promisor = function () use ($promise) { return $promise; };
        
        $lazy = new LazyPromise($promisor);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $lazy->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testPromisorReturnsRejectedPromise()
    {
        $exception = new Exception();
        $promise = Promise::reject($exception);
        
        $promisor = function () use ($promise) { return $promise; };
        
        $lazy = new LazyPromise($promisor);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $lazy->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testPromisorReturnsPendingPromise()
    {
        $promise = new Promise(function () {});
        
        $promisor = function () use ($promise) { return $promise; };
        
        $lazy = new LazyPromise($promisor);
        
        $lazy->done($this->createCallback(0), $this->createCallback(0));
        
        $this->assertTrue($lazy->isPending());
    }
    
    /**
     * @depends testPromisorCalledWhenDoneCalled
     */
    public function testResolvePromiseWithLazyPromise()
    {
        $value = 'test';
        
        $promise = new Promise(function ($resolve) use ($value) {
            $promise = Promise::resolve($value);
            $promisor = function () use ($promise) { return $promise; };
            $resolve(new LazyPromise($promisor));
        });
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
}
