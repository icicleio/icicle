<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Awaitable;
use Icicle\Awaitable\Exception\MultiReasonException;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class SomeTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(InvalidArgumentError::class));
        
        Awaitable\some([], 1)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testRequireZeroFulfillsWithEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([]));
        
        Awaitable\some([1], 0)->done($callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([1, 2]));
        
        Awaitable\some([1, 2, 3], 2)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfilledPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([0 => 1, 1 => 2]));
        
        Awaitable\some($promises, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testPendingPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [
            Awaitable\resolve(1)->delay(0.2),
            Awaitable\resolve(2)->delay(0.3),
            Awaitable\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([0 => 1, 2 => 3]));
        
        Awaitable\some($promises, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testRejectIfTooManyPromisesAreRejected()
    {
        $exception = new Exception();
        $promises = [Awaitable\reject($exception), Awaitable\resolve(2), Awaitable\reject($exception)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(MultiReasonException::class));
        
        Awaitable\some($promises, 2)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testFulfillImmediatelyWhenEnoughPromisesAreFulfilled()
    {
        $exception = new Exception();
        $promises = [
            Awaitable\reject($exception),
            Awaitable\resolve(2),
            Awaitable\reject($exception),
            Awaitable\resolve(4),
            Awaitable\resolve(5)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([1 => 2, 3 => 4]));
        
        Awaitable\some($promises, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testArrayKeysPreservedOnRejected()
    {
        $exception = new Exception();
        $promises = [
            'one' => Awaitable\reject($exception),
            'two' => Awaitable\resolve(2),
            'three' => Awaitable\reject($exception)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($exception) use ($promises) {
            $reasons = $exception->getReasons();
            ksort($reasons);
            return array_keys($reasons) === ['one', 'three'];
        }));
        
        Awaitable\some($promises, 2)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
