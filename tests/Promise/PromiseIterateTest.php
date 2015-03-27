<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class PromiseIterateTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
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
        
        Promise::iterate($this->createCallback(0), $predicate, $seed)
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $this->assertSame($seed, $parameter);
    }
    
    /**
     * @depends testSeedReturnedWhenPredicateImmediatelyReturnsFalse
     */
    public function testFulfilledPromiseAsSeed()
    {
        $seed = 1;
        $promise = Promise::resolve($seed);
        
        $predicate = function ($value) use (&$parameter) {
            $parameter = $value;
            return false;
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($seed));
        
        Promise::iterate($this->createCallback(0), $predicate, $promise)
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
        
        $this->assertSame($seed, $parameter);
    }
    
    /**
     * @depends testSeedReturnedWhenPredicateImmediatelyReturnsFalse
     */
    public function testRejectedPromiseAsSeed()
    {
        $exception = new Exception();
        $promise = Promise::reject($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise::iterate($this->createCallback(0), $this->createCallback(0), $promise)
               ->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testRejectedWhenPredicateThrowsException()
    {
        $exception = new Exception();
        
        $predicate = $this->createCallback(1);
        $predicate->method('__invoke')
                  ->will($this->throwException($exception));
        
        Promise::iterate($this->createCallback(0), $predicate, 1);
        
        Loop::run();
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
        
        Promise::iterate($worker, $predicate, 1);
        
        Loop::run();
    }
    
    public function testWorkerReturnsFulfilledPromise()
    {
        $predicate = function () {
            static $count = 2;
            return 0 !== --$count;
        };
        
        $worker = function ($value) {
            return Promise::resolve($value + 1);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Promise::iterate($worker, $predicate, 1)
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testWorkerReturnsRejectedPromise()
    {
        $exception = new Exception();
        
        $predicate = function ($value) {
            static $count = 2;
            return 0 !== --$count;
        };
        
        $worker = function ($value) use ($exception) {
            return Promise::reject($exception);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise::iterate($worker, $predicate, 1)
               ->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testWorkerReturnsPendingPromise()
    {
        $predicate = function ($value) {
            static $count = 2;
            return 0 !== --$count;
        };
        
        $worker = function ($value) {
            return Promise::resolve($value + 1)->delay(0.1);
        };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Promise::iterate($worker, $predicate, 1)
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
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

        Promise::iterate($worker, $predicate, $seed)
               ->done($callback, $this->createCallback(0));

        Loop::run();
    }
}
