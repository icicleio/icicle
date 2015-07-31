<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Promise\Structures;

use Icicle\Promise\Structures\ThenQueue;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class ThenQueueTest extends TestCase
{
    public function testInvoke()
    {
        $queue = new ThenQueue();
        
        $value = 'test';
        
        $callback = $this->createCallback(3);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $queue->push($callback);
        $queue->push($callback);
        $queue->push($callback);
        
        $queue($value);
    }
}
