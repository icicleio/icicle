<?php
namespace Icicle\Tests\Promise\Structures;

use Icicle\Promise\Structures\ThenQueue;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class ThenQueueTest extends TestCase
{
    public function setUp()
    {
        $this->queue = new ThenQueue();
    }
    
    public function testInsert()
    {
        $this->queue->insert($this->createCallback(0));
        
        $this->assertFalse($this->queue->isEmpty());
        $this->assertSame(1, $this->queue->count());
    }
    
    /**
     * @depends testInsert
     */
    public function testInvoke()
    {
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        
        $this->queue->insert($callback);
        
        $queue = $this->queue;
        $queue($value);
    }
    
    /**
     * @depends testInvoke
     */
    public function testClear()
    {
        $this->queue->insert($this->createCallback(0));
        $this->queue->insert($this->createCallback(0));
        
        $this->assertFalse($this->queue->isEmpty());
        $this->assertSame(2, $this->queue->count());
        
        $this->queue->clear();
        
        $this->assertTrue($this->queue->isEmpty());
        $this->assertSame(0, $this->queue->count());
        
        $this->queue->insert($this->createCallback(1));
        
        $this->queue->__invoke(1);
    }
}
