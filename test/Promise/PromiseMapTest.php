<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class PromiseMapTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([]));
        
        Promise::map([], $this->createCallback(0))
            ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([2, 3, 4]));
        
        Promise::map($values, function ($value) { return $value + 1; })
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testFulfilledPromisesArray()
    {
        $promises = [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([2, 3, 4]));
        
        Promise::map($promises, function ($value) { return $value + 1; })
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testPendingPromisesArray()
    {
        $promises = [
            Promise::resolve(1)->delay(0.2),
            Promise::resolve(2)->delay(0.3),
            Promise::resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([2, 3, 4]));
        
        Promise::map($promises, function ($value) { return $value + 1; })
               ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testRejectOnFirstRejected()
    {
        $exception = new Exception();
        $promises = [Promise::resolve(1), Promise::resolve(2), Promise::reject($exception)];
        
        $mapper = function ($value) { return $value; };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise::map($promises, $mapper)->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testCallbackThrowingExceptionRejectsPromise()
    {
        $exception = new Exception();
        
        $mapper = function () use ($exception) { throw $exception; };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise::map([1, 2, 3], $mapper)->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
}
