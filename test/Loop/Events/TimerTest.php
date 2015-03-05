<?php
namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\Timer;
use Icicle\Tests\TestCase;

class TimerTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $loop;
    
    public function setUp()
    {
        $this->loop = $this->createLoop();
    }
    
    protected function createLoop()
    {
        $loop = $this->getMockBuilder('Icicle\Loop\LoopInterface')
                     ->getMock();
        
        $loop->method('createTimer')
             ->will($this->returnCallback(
                 function (callable $callback, $interval, $periodic = false, array $args = null) use ($loop) {
                     return new Timer($loop, $callback, $interval, $periodic, $args);
                 }));
        
        return $loop;
    }
    
    public function testGetCallback()
    {
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT);
        
        $callback = $timer->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback();
    }
    
    public function testGetInterval()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT);
        
        $this->assertSame(self::TIMEOUT, $timer->getInterval());
    }
    
    /**
     * @depends testGetInterval
     */
    public function testInvalidInterval()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), -1);
        
        $this->assertGreaterThanOrEqual(0, $timer->getInterval());
    }
    
    public function testCall()
    {
        $timer = $this->loop->createTimer($this->createCallback(2), self::TIMEOUT);
        
        $timer->call();
        $timer->call();
    }
    
    /**
     * @depends testCall
     */
    public function testInvoke()
    {
        $timer = $this->loop->createTimer($this->createCallback(2), self::TIMEOUT);
        
        $timer();
        $timer();
    }
    
    public function testIsPending()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT);
        
        $this->loop->expects($this->once())
                   ->method('isTimerPending')
                   ->with($this->identicalTo($timer))
                   ->will($this->returnValue(true));
        
        $this->assertTrue($timer->isPending());
    }
    
    public function testIsPeriodic()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT, true);
        
        $this->assertTrue($timer->isPeriodic());
        
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT, false);
        
        $this->assertFalse($timer->isPeriodic());
    }
    
    public function testCancel()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT);
        
        $this->loop->expects($this->once())
                   ->method('cancelTimer')
                   ->with($this->identicalTo($timer));
        
        $timer->cancel();
    }
    
    public function testUnreference()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT);
        
        $this->loop->expects($this->once())
                   ->method('unreferenceTimer')
                   ->with($this->identicalTo($timer));
        
        $timer->unreference();
    }
    
    public function testReference()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT);
        
        $this->loop->expects($this->once())
                   ->method('referenceTimer')
                   ->with($this->identicalTo($timer));
        
        $timer->reference();
    }
    
    /**
     * @depends testCall
     */
    public function testArguments()
    {
        $arg1 = 1;
        $arg2 = 2;
        $arg3 = 3;
        $arg4 = 4;
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(
                     $this->identicalTo($arg1),
                     $this->identicalTo($arg2),
                     $this->identicalTo($arg3),
                     $this->identicalTo($arg4)
                 );
        
        $timer = $this->loop->createTimer($callback, self::TIMEOUT, false, [$arg1, $arg2, $arg3, $arg4]);
        
        $timer->call();
    }
}
