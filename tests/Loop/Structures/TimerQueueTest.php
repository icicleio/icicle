<?php
namespace Icicle\Tests\Structures;

use Icicle\Tests\TestCase;
use Icicle\Loop\Structures\TimerQueue;

class TimerQueueTest extends TestCase
{
    const TIMEOUT = 0.1;
    const MICROSEC_PER_SEC = 1e6;
    
    protected $queue;
    
    public function setUp()
    {
        $this->queue = new TimerQueue();
    }
    
    public function createTimer($callback, $interval = self::TIMEOUT, $periodic = false, array $args = null)
    {
        $timer = $this->getMockBuilder('Icicle\Loop\Events\TimerInterface')
                      ->getMock();
        
        if (!empty($args)) {
            $callback = function () use ($callback, $args) {
                call_user_func_array($callback, $args);
            };
        }
        
        $timer->method('getInterval')
              ->will($this->returnValue($interval));
        
        $timer->method('isPeriodic')
              ->will($this->returnValue((bool) $periodic));
        
        $timer->method('getCallback')
              ->will($this->returnValue($callback));
        
        $timer->method('call')
              ->will($this->returnCallback($callback));
        
        return $timer;
    }
    
    public function testAdd()
    {
        $this->queue->add($timer1 = $this->createTimer($this->createCallback(0)));
        $this->queue->add($timer2 = $this->createTimer($this->createCallback(0)));
        $this->queue->add($timer3 = $this->createTimer($this->createCallback(0)));
        
        $this->assertTrue($this->queue->contains($timer1));
        $this->assertTrue($this->queue->contains($timer2));
        $this->assertTrue($this->queue->contains($timer3));
        
        $this->assertEquals(3, $this->queue->count());
    }
    
    /**
     * @depends testAdd
     */
    public function testGetInterval()
    {
        $this->assertNull($this->queue->getInterval());
        
        $start = microtime(true);
        
        $timer = $this->createTimer($this->createCallback(0));
        
        $this->queue->add($timer);
        
        $interval = $this->queue->getInterval();
        
        $timeout = self::TIMEOUT - (microtime(true) - $start);
        
        $this->assertGreaterThanOrEqual($timeout, $interval);
        $this->assertLessThanOrEqual(self::TIMEOUT, $interval);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(0, $this->queue->getInterval());
    }
    
    /**
     * @depends testAdd
     */
    public function testTick()
    {
        $timer = $this->createTimer($this->createCallback(1));
        
        $this->queue->add($timer);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(1, $this->queue->tick());
        
        $this->assertFalse($this->queue->contains($timer));
        
        $this->assertTrue($this->queue->isEmpty());
    }
    
    /**
     * @depends testTick
     */
    public function testPeriodic()
    {
        $timer = $this->createTimer($this->createCallback(3), self::TIMEOUT, true);
        
        $this->queue->add($timer);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(1, $this->queue->tick());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(1, $this->queue->tick());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(1, $this->queue->tick());
    }
    
    /**
     * @depends testTick
     */
    public function testMultipleAdd()
    {
        $timer = $this->createTimer($this->createCallback(1));
        
        $this->queue->add($timer);
        $this->queue->add($timer); // Should ignore second add.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(1, $this->queue->tick());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(0, $this->queue->tick()); // Should have nothing to call.
    }
    
    /**
     * @depends testTick
     */
    public function testRemove()
    {
        $timer = $this->createTimer($this->createCallback(0), self::TIMEOUT * 2);
        $this->queue->add($timer);
        
        $this->assertGreaterThan(self::TIMEOUT, $this->queue->getInterval());
        
        $this->queue->remove($timer);
        
        $this->assertFalse($this->queue->contains($timer));
        $this->assertSame(0, $this->queue->count());
        $this->assertNull($this->queue->getInterval());
        
        $timer = $this->createTimer($this->createCallback(0), self::TIMEOUT / 2);
        $this->queue->add($timer);
        $this->queue->add($this->createTimer($this->createCallback(1)));
        $this->queue->add($this->createTimer($this->createCallback(1)));
        
        $this->assertLessThan(self::TIMEOUT / 2, $this->queue->getInterval());
        
        $this->queue->remove($timer);
        
        $this->assertGreaterThan(self::TIMEOUT / 2, $this->queue->getInterval());
        $this->assertLessThan(self::TIMEOUT, $this->queue->getInterval());
        
        $timer = $this->createTimer($this->createCallback(0), self::TIMEOUT);
        $this->queue->add($timer);
        
        $this->assertTrue($this->queue->contains($timer));
        $this->assertSame(3, $this->queue->count());
        
        $this->queue->remove($timer);
        
        $this->assertFalse($this->queue->contains($timer));
        $this->assertSame(2, $this->queue->count());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(2, $this->queue->tick());
    }
    
    /**
     * @depends testTick
     */
    public function testUnreference()
    {
        $timer = $this->createTimer($this->createCallback(0), self::TIMEOUT, true);
        
        $this->queue->add($timer);
        
        $this->queue->unreference($timer);
        
        $this->assertSame(0, $this->queue->count());
        
        $this->assertFalse($this->queue->isEmpty());
        
        $this->queue->add($this->createTimer($this->createCallback(0), self::TIMEOUT, true));
        
        $this->assertSame(1, $this->queue->count());
        
        $this->queue->reference($timer);
        
        $this->assertSame(2, $this->queue->count());
    }
    
    public function testClear()
    {
        $this->queue->add($this->createTimer($this->createCallback(0)));
        $this->queue->add($this->createTimer($this->createCallback(0)));
        $this->queue->add($this->createTimer($this->createCallback(0)));
        
        $this->queue->clear();
        
        $this->assertTrue($this->queue->isEmpty());
    }
}
