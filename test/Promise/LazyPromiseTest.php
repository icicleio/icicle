<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\LazyPromise;
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
    public function testCall()
    {
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $lazy = LazyPromise::call($callback, $value);
        
        $this->assertInstanceOf('Icicle\Promise\LazyPromise', $lazy);
        
        $lazy->done();
    }
}
