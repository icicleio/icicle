<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class PromisePromisifyTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testFunctionOnlyTakingACallback()
    {
        $worker = function (callable $callback) {
            return $callback(1, 2, 3);
        };
        
        $promisified = Promise::promisify($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified()->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testFunctionWithCallbackAsFirstParameter()
    {
        $worker = function (callable $callback, $value1, $value2, $value3) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise::promisify($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(1, 2, 3)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testFunctionWithCallbackAsMidParameter()
    {
        $worker = function ($value1, callable $callback, $value2, $value3) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise::promisify($worker, 1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(1, 2, 3)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testFunctionWithCallbackAsLastParameter()
    {
        $worker = function ($value1, $value2, $value3, callable $callback) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise::promisify($worker, 3);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(1, 2, 3)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
/*
    public function testFulfilledPromisesAsArguments()
    {
        $worker = function ($value1, $value2, $value3, callable $callback) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise::promisify($worker, 3);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(
            Promise::resolve(1),
            Promise::resolve(2),
            Promise::resolve(3)
        )->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testPendingPromisesAsArguments()
    {
        $worker = function ($value1, $value2, $value3, callable $callback) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise::promisify($worker, 3);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(
            Promise::resolve(1)->delay(0.2),
            Promise::resolve(2)->delay(0.3),
            Promise::resolve(3)->delay(0.1)
        )->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testRejectedPromisesAsArguments()
    {
        $exception = new Exception();
        
        $worker = function ($value1, $value2, $value3, callable $callback) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise::promisify($worker, 3);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promisified(
            Promise::resolve(1),
            Promise::reject($exception),
            Promise::resolve(3)
        )->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
*/
    
    public function testReturnedPromiseRejectedWhenWorkerThrowsException()
    {
        $exception = new Exception();
        
        $worker = function (callable $callback) use ($exception) {
            throw $exception;
        };
        
        $promisified = Promise::promisify($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promisified()->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testTooFewArguments()
    {
        $worker = function ($value1, $value1, callable $callback) {
            return $callback($value1, $value2);
        };
        
        $promisified = Promise::promisify($worker, 2);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\LogicException'));
        
        $promisified()->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
}
