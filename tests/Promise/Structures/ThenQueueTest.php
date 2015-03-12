<?php
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
