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
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class AllTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([]));
        
        Awaitable\all([])->done($callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo($values));
        
        Awaitable\all($values)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfilledPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo($values));
        
        Awaitable\all($promises)->done($callback);
        
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
                 ->with($this->equalTo($values));
        
        Awaitable\all($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testArrayKeysPreserved()
    {
        $values = ['one' => 1, 'two' => 2, 'three' => 3];
        $promises = [
            'one' => Awaitable\resolve(1)->delay(0.2),
            'two' => Awaitable\resolve(2)->delay(0.3),
            'three' => Awaitable\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($result) use ($promises) {
            ksort($result);
            ksort($promises);
            return array_keys($result) === array_keys($promises);
        }));
        
        Awaitable\all($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testRejectIfInputPromiseIsRejected()
    {
        $exception = new Exception();
        $promises = [Awaitable\resolve(1), Awaitable\reject($exception), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\all($promises)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
