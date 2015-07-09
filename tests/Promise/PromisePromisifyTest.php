<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\Exception\InvalidArgumentError;
use Icicle\Tests\TestCase;

class PromisePromisifyTest extends TestCase
{
    public function tearDown()
    {
        Loop\clear();
    }
    
    public function testFunctionOnlyTakingACallback()
    {
        $worker = function (callable $callback) {
            return $callback(1, 2, 3);
        };
        
        $promisified = Promise\promisify($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified()->done($callback);
        
        Loop\run();
    }
    
    public function testFunctionWithCallbackAsFirstParameter()
    {
        $worker = function (callable $callback, $value1, $value2, $value3) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise\promisify($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(1, 2, 3)->done($callback);
        
        Loop\run();
    }
    
    public function testFunctionWithCallbackAsMidParameter()
    {
        $worker = function ($value1, callable $callback, $value2, $value3) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise\promisify($worker, 1);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(1, 2, 3)->done($callback);
        
        Loop\run();
    }
    
    public function testFunctionWithCallbackAsLastParameter()
    {
        $worker = function ($value1, $value2, $value3, callable $callback) {
            return $callback($value1, $value2, $value3);
        };
        
        $promisified = Promise\promisify($worker, 3);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([1, 2, 3]));
        
        $promisified(1, 2, 3)->done($callback);
        
        Loop\run();
    }

    public function testReturnedPromiseRejectedWhenWorkerThrowsException()
    {
        $exception = new Exception();
        
        $worker = function (callable $callback) use ($exception) {
            throw $exception;
        };
        
        $promisified = Promise\promisify($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promisified()->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testTooFewArguments()
    {
        $worker = function ($value1, $value2, callable $callback) {
            return $callback($value1, $value2);
        };
        
        $promisified = Promise\promisify($worker, 2);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(InvalidArgumentError::class));
        
        $promisified()->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
