<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
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

class LiftTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testNoArguments()
    {
        $worker = function () { return 1; };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $lifted()->done($callback);
        
        Loop\run();
    }
    
    public function testValueArguments()
    {
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(-1));
        
        $lifted(1, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfilledPromiseArguments()
    {
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(-1));
        
        $lifted(Awaitable\resolve(1), Awaitable\resolve(2))
            ->done($callback);
        
        Loop\run();
    }
    
    public function testPendingPromiseArguments()
    {
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(-1));
        
        $lifted(
            Awaitable\resolve(1)->delay(0.2),
            Awaitable\resolve(2)->delay(0.1)
        )
        ->done($callback);
        
        Loop\run();
    }
    
    public function testRejectedPromiseArguments()
    {
        $exception = new Exception();
        
        $worker = function ($left, $right) {
            return $left - $right;
        };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $lifted(Awaitable\resolve(1), Awaitable\reject($exception))
            ->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testLiftedFunctionReturnsPromise()
    {
        $promise = Awaitable\resolve(1);
        
        $worker = function () use ($promise) {
            return $promise;
        };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        $lifted()->done($callback);
        
        Loop\run();
    }
    
    public function testRejectIfLiftedFunctionThrowsException()
    {
        $exception = new Exception();
        
        $worker = function () use ($exception) {
            throw $exception;
        };
        
        $lifted = Awaitable\lift($worker);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $lifted()->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
