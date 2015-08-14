<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Promise\Exception\InvalidArgumentError;
use Icicle\Tests\TestCase;

class PromiseChooseTest extends TestCase
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
        
        Promise\choose([])->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Promise\choose($values)->done($callback);
        
        Loop\run();
    }
    
    public function testPromisesArray()
    {
        $promises = [Promise\resolve(1), Promise\resolve(2), Promise\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Promise\choose($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfillOnFirstFulfilled()
    {
        $promises = [Promise\resolve(1)->delay(0.3), Promise\resolve(2)->delay(0.1), Promise\resolve(3)->delay(0.2)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Promise\choose($promises)->done($callback);
        
        Loop\run();
    }
    
    public function testRejectOnFirstRejected()
    {
        $exception = new Exception();
        $promises = [Promise\resolve(1)->delay(0.2), Promise\reject($exception), Promise\resolve(3)->delay(0.1)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        Promise\choose($promises)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
