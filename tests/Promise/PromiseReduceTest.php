<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Tests\TestCase;

class PromiseReduceTest extends TestCase
{
    public function tearDown()
    {
        Loop\clear();
    }
    
    public function testEmptyArrayWithNoInitial()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(null));
        
        Promise\reduce([], $this->createCallback(0))
               ->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testEmptyArrayWithInitial()
    {
        $initial = 1;
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($initial));
        
        Promise\reduce([], $this->createCallback(0), $initial)
               ->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Promise\reduce($values, function ($carry, $value) { return $carry + $value; }, 0)
               ->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testPromisesArray()
    {
        $promises = [Promise\resolve(1), Promise\resolve(2), Promise\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Promise\reduce($promises, function ($carry, $value) { return $carry + $value; }, 0)
               ->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testPendingPromisesArray()
    {
        $promises = [
            Promise\resolve(1)->delay(0.2),
            Promise\resolve(2)->delay(0.3),
            Promise\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Promise\reduce($promises, function ($carry, $value) { return $carry + $value; }, 0)
               ->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testFulfilledPromiseAsInitial()
    {
        $values = [1, 2, 3];
        $initial = Promise\resolve(4);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(10));
        
        Promise\reduce($values, function ($carry, $value) { return $carry + $value; }, $initial)
               ->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testRejectedPromiseAsInitial()
    {
        $exception = new Exception();
        $values = [1, 2, 3];
        $initial = Promise\reject($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\reduce($values, function ($carry, $value) { return $carry + $value; }, $initial)
               ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testRejectOnFirstRejected()
    {
        $exception = new Exception();
        $promises = [Promise\resolve(1), Promise\reject($exception), Promise\resolve(3)];
        
        $mapper = function ($value) { return $value; };
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\reduce($promises, function() {}, 0)
               ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testCallbackReturnsFulfilledPromise()
    {
        $promises = [Promise\resolve(1), Promise\resolve(2), Promise\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(6));
        
        Promise\reduce(
            $promises,
            function ($carry, $value) {
                return Promise\resolve($carry + $value);
            },
            0
        )->done($callback, $this->createCallback(0));
        
        Loop\run();
    }
    
    public function testCallbackReturnsRejectedPromise()
    {
        $exception = new Exception();
        $promises = [Promise\resolve(1), Promise\resolve(2)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\reduce(
            $promises,
            function () use ($exception) {
                return Promise\reject($exception);
            },
            0
        )->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testCallbackThrowsException()
    {
        $exception = new Exception();
        $promises = [Promise\resolve(1), Promise\resolve(2)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\reduce(
            $promises,
            function ($carry, $value) use ($exception) {
                throw $exception;
            },
            0
        )->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
