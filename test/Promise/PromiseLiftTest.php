<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class PromiseLiftTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testNoArguments()
    {
        $worker = function () { return 1; };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $lifted()->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testValueArguments()
    {
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(-1));
        
        $lifted(1, 2)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testFulfilledPromiseArguments()
    {
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(-1));
        
        $lifted(Promise::resolve(1), Promise::resolve(2))
            ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testPendingPromiseArguments()
    {
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(-1));
        
        $lifted(
            Promise::resolve(1)->delay(0.2),
            Promise::resolve(2)->delay(0.1)
        )
        ->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testRejectedPromiseArguments()
    {
        $exception = new Exception();
        
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $lifted(Promise::resolve(1), Promise::reject($exception))
            ->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testLiftedFunctionReturnsPromise()
    {
        $promise = Promise::resolve(1);
        
        $worker = function () use ($promise) {
            return $promise;
        };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $lifted()->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testRejectIfLiftedFunctionThrowsException()
    {
        $exception = new Exception();
        
        $worker = function () use ($exception) {
            throw $exception;
        };
        
        $lifted = Promise::lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $lifted()->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
}
