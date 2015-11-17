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
use Icicle\Awaitable\Exception\InvalidArgumentError;
use Icicle\Awaitable\Exception\MultiReasonException;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class AnyTest extends TestCase
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
        
        Awaitable\any([])->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Awaitable\any($values)->done($callback);
        
        Loop\run();
    }
    
    public function testPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [Awaitable\resolve(1), Awaitable\resolve(2), Awaitable\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Awaitable\any($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfillOnFirstInputPromiseFulfilled()
    {
        $exception = new Exception();
        $promises = [Awaitable\reject($exception), Awaitable\resolve(2), Awaitable\reject($exception)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Awaitable\any($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testRejectIfAllInputPromisesAreRejected()
    {
        $exception = new Exception();
        $promises = [Awaitable\reject($exception), Awaitable\reject($exception), Awaitable\reject($exception)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(MultiReasonException::class));
        
        Awaitable\any($promises)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testArrayKeysPreserved()
    {
        $exception = new Exception();
        $promises = [
            'one' => Awaitable\reject($exception),
            'two' => Awaitable\reject($exception),
            'three' => Awaitable\reject($exception)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($exception) use ($promises) {
            $reasons = $exception->getReasons();
            ksort($reasons);
            ksort($promises);
            return array_keys($reasons) === array_keys($promises);
        }));
        
        Awaitable\any($promises)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
