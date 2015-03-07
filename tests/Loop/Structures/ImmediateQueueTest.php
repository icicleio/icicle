<?php
namespace Icicle\Tests\Structures;

use Icicle\Tests\TestCase;
use Icicle\Loop\Structures\ImmediateQueue;

class ImmediateQueueTest extends TestCase
{
    protected $queue;
    
    public function setUp()
    {
        $this->queue = new ImmediateQueue();
    }
    
    public function createImmediate($callback, array $args = null)
    {
        $immediate = $this->getMockBuilder('Icicle\Loop\Events\ImmediateInterface')
                          ->getMock();
        
        if (!empty($args)) {
            $callback = function () use ($callback, $args) {
                call_user_func_array($callback, $args);
            };
        }
        
        $immediate->method('getCallback')
                  ->will($this->returnValue($callback));
        
        $immediate->method('call')
                  ->will($this->returnCallback($callback));
        
        return $immediate;
    }
    
    public function testAdd()
    {
        $this->queue->add($immediate1 = $this->createImmediate($this->createCallback(0)));
        $this->queue->add($immediate2 = $this->createImmediate($this->createCallback(0)));
        $this->queue->add($immediate3 = $this->createImmediate($this->createCallback(0)));
        
        $this->assertTrue($this->queue->contains($immediate1));
        $this->assertTrue($this->queue->contains($immediate2));
        $this->assertTrue($this->queue->contains($immediate3));
        
        $this->assertEquals(3, $this->queue->count());
    }
    
    /**
     * @depends testAdd
     */
    public function testTick()
    {
        $immediate1 = $this->createImmediate($this->createCallback(1));
        $this->queue->add($immediate1);
        
        $immediate2 = $this->createImmediate($this->createCallback(1));
        $this->queue->add($immediate2);
        
        $this->assertTrue($this->queue->tick());
        
        $this->assertFalse($this->queue->contains($immediate1));
        $this->assertTrue($this->queue->contains($immediate2));
        
        $this->assertTrue($this->queue->tick());
        
        $this->assertTrue($this->queue->isEmpty());
        
        $this->assertFalse($this->queue->tick());
    }
    
    /**
     * @depends testTick
     */
    public function testMultipleAdd()
    {
        $immediate = $this->createImmediate($this->createCallback(1));
        
        $this->queue->add($immediate);
        $this->queue->add($immediate); // Should ignore second add.
        
        $this->assertTrue($this->queue->tick());
        
        $this->assertTrue($this->queue->isEmpty());
        $this->assertFalse($this->queue->contains($immediate));
        
        $this->assertFalse($this->queue->tick()); // Should have nothing to call.
    }
    
    /**
     * @depends testTick
     */
    public function testRemove()
    {
        $immediate = $this->createImmediate($this->createCallback(0));
        
        $this->queue->add($this->createImmediate($this->createCallback(1)));
        $this->queue->add($immediate);
        $this->queue->add($this->createImmediate($this->createCallback(1)));
        $this->queue->remove($immediate);
        
        $this->assertFalse($this->queue->contains($immediate));
        
        $this->assertSame(2, $this->queue->count());
        
        $this->assertTrue($this->queue->tick());
        $this->assertTrue($this->queue->tick());
        $this->assertFalse($this->queue->tick());
    }
    
    public function testClear()
    {
        $this->queue->add($this->createImmediate($this->createCallback(0)));
        $this->queue->add($this->createImmediate($this->createCallback(0)));
        $this->queue->add($this->createImmediate($this->createCallback(0)));
        
        $this->queue->clear();
        
        $this->assertTrue($this->queue->isEmpty());
    }
}
