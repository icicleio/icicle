<?php
namespace Icicle\Tests\Timer;

use Icicle\Tests\TestCase;
use Icicle\Timer\TimerQueue;

class TimerQueueTest extends TestCase
{
    const TIMEOUT = 0.1;
    const MICROSEC_PER_SEC = 1e6;
    
    protected $queue;
    
    public function setUp()
    {
        $this->queue = new TimerQueue();
    }
    
    public function createTimer($periodic = false)
    {
        $timer = $this->getMockBuilder('Icicle\Timer\Timer')
                      ->disableOriginalConstructor()
                      ->getMock();
        
        $timer->method('getInterval')
              ->will($this->returnValue(self::TIMEOUT));
        
        $timer->method('isPeriodic')
              ->will($this->returnValue((bool) $periodic));
        
        return $timer;
    }
    
    public function testAdd()
    {
        $this->queue->add($timer1 = $this->createTimer(false));
        $this->queue->add($timer2 = $this->createTimer(false));
        $this->queue->add($timer3 = $this->createTimer(false));
        
        $this->assertTrue($this->queue->contains($timer1));
        $this->assertTrue($this->queue->contains($timer2));
        $this->assertTrue($this->queue->contains($timer3));
        
        $this->assertEquals(3, $this->queue->count());
    }
    
    public function testGetInterval()
    {
        $start = microtime(true);
        
        $timer = $this->createTimer(false);
        
        $this->queue->add($timer);
        
        $interval = $this->queue->getInterval();
        
        $timeout = self::TIMEOUT - (microtime(true) - $start);
        
        $this->assertGreaterThanOrEqual($timeout, $interval);
        $this->assertLessThanOrEqual(self::TIMEOUT, $interval);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->assertSame(0, $this->queue->getInterval());
    }
    
    public function testTick()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $this->queue->add($timer);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick();
        
        $this->assertFalse($this->queue->contains($timer));
        
        $this->assertTrue($this->queue->isEmpty());
    }
    
    public function testPeriodic()
    {
        $timer = $this->createTimer(true);
        $timer->expects($this->exactly(3))
              ->method('call');
        
        $this->queue->add($timer);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick();
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick();
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick();
    }
    
    public function testMultipleAdd()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $this->queue->add($timer);
        $this->queue->add($timer); // Should ignore second add.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick();
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick(); // Should have nothing to call.
    }
    
    public function testRemove()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->never())
              ->method('call');
        
        $this->queue->add($this->createTimer(false));
        $this->queue->add($timer);
        $this->queue->add($this->createTimer(false));
        $this->queue->remove($timer);
        
        $this->assertFalse($this->queue->contains($timer));
        
        $this->assertEquals(2, $this->queue->count());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->queue->tick();
    }
    
    public function testUnreference()
    {
        $timer = $this->createTimer(false);
        
        $this->queue->add($timer);
        
        $this->queue->unreference($timer);
        
        $this->assertEquals(0, $this->queue->count());
        $this->assertFalse($this->queue->isEmpty());
        
        $this->queue->add($this->createTimer(false));
        
        $this->queue->reference($timer);
        
        $this->assertEquals(2, $this->queue->count());
    }
    
    public function testClear()
    {
        $this->queue->add($this->createTimer(false));
        $this->queue->add($this->createTimer(false));
        $this->queue->add($this->createTimer(false));
        
        $this->queue->clear();
        
        $this->assertTrue($this->queue->isEmpty());
    }
}
