<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Awaitable\Internal;

use Icicle\Awaitable\Internal\DoneQueue;
use Icicle\Tests\TestCase;

class DoneQueueTest extends TestCase
{
    public function testInvoke()
    {
        $queue = new DoneQueue();
        
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
