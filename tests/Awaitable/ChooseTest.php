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
use Icicle\Awaitable\Exception\InvalidArgumentError;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class ChooseTest extends TestCase
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
        
        Awaitable\choose([])->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Awaitable\choose($values)->done($callback);
        
        Loop\run();
    }
    
    public function testPromisesArray()
    {
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Awaitable\choose($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfillOnFirstFulfilled()
    {
        $promises = [Awaitable\resolve(1)->delay(0.3), Awaitable\resolve(2)->delay(0.1), Awaitable\resolve(3)->delay(0.2)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Awaitable\choose($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testRejectOnFirstRejected()
    {
        $exception = new Exception();
        $promises = [Awaitable\resolve(1)->delay(0.2), Awaitable\reject($exception), Awaitable\resolve(3)->delay(0.1)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Awaitable\choose($promises)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
