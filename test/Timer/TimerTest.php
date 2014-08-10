<?php
namespace Icicle\Tests\Timer;

use Icicle\Loop\Loop;
use Icicle\Tests\TestCase;
use Icicle\Timer\Timer;

class TimerTest extends TestCase
{
    const TIMEOUT = 0.1;
    const MICROSEC_PER_SEC = 1e6;
    
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testOnce()
    {
        $timer = Timer::once($this->createCallback(0), self::TIMEOUT);
        $this->assertInstanceOf('Icicle\Timer\Timer', $timer);
        $this->assertFalse($timer->isPeriodic());
    }
    
    public function testPeriodic()
    {
        $timer = Timer::periodic($this->createCallback(0), self::TIMEOUT);
        $this->assertInstanceOf('Icicle\Timer\Timer', $timer);
        $this->assertTrue($timer->isPeriodic());
    }
    
    /**
     * @depends testOnce
     */
    public function testGetInterval()
    {
        $timer = Timer::once($this->createCallback(0), self::TIMEOUT);
        
        $this->assertEquals(self::TIMEOUT, $timer->getInterval());
    }
    
    /**
     * @depends testGetInterval
     */
    public function testCancel()
    {
        $once = Timer::once($this->createCallback(0), self::TIMEOUT);
        $periodic = Timer::periodic($this->createCallback(2), self::TIMEOUT);
        
        $once->cancel();
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $periodic->cancel();
        
        Loop::tick(false);
    }
    
    /**
     * @depends testPeriodic
     */
    public function testSet()
    {
        $timer = Timer::periodic($this->createCallback(3), self::TIMEOUT);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false); // Should call timer.
        
        $timer->set(self::TIMEOUT * 2, false); // Set new timeout and make non-periodic.
        
        $this->assertSame(self::TIMEOUT * 2, $timer->getInterval());
        $this->assertFalse($timer->isPeriodic());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false); // Should not call timer.
        
        $interval = $timer->getInterval();
        $periodic = $timer->isPeriodic();
        
        $timer->set(); // Reset timer with previous attributes.
        
        $this->assertSame($interval, $timer->getInterval());
        $this->assertSame($periodic, $timer->isPeriodic());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false); // Should not call timer.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false); // Should call timer.
        
        $this->assertFalse($timer->isPending());
        
        $timer->set(self::TIMEOUT, true); // Set back to original timeout and make periodic again.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false); // Should call timer.
        
        $this->assertTrue($timer->isPending());
    }
    
    /**
     * @depends testSet
     */
    public function testInvoke()
    {
        $timer = Timer::periodic($this->createCallback(1), self::TIMEOUT);
        
        $timer(self::TIMEOUT / 2, false);
        
        $this->assertSame(self::TIMEOUT / 2, $timer->getInterval());
        $this->assertFalse($timer->isPeriodic());
        
        usleep(self::TIMEOUT / 2 * self::MICROSEC_PER_SEC);
        
        Loop::tick(false);
    }
    
    /**
     * @depends testSet
     */
    public function testMinInterval()
    {
        $timer = Timer::once($this->createCallback(0), 0);
        
        $this->assertSame(Timer::MIN_INTERVAL, $timer->getInterval());
        
        $timer->set(0);
        
        $this->assertSame(Timer::MIN_INTERVAL, $timer->getInterval());
    }
    
    /**
     * @depends testPeriodic
     * @depends testCancel
     */
    public function testIntervalWithSelfCancel()
    {
        $iterations = 3;
        
        $callback = $this->createCallback($iterations);
        
        $timer = Timer::periodic(function () use ($callback, $iterations, &$timer) {
            static $count = 0;
            ++$count;
            
            $callback();
            
            if ($iterations === $count) {
                $timer->cancel();
            }
        }, self::TIMEOUT);
        
        Loop::run();
    }
    
    /**
     * @depends testGetInterval
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
        
        $timer = Timer::once($callback, self::TIMEOUT, $arg1, $arg2, $arg3, $arg4);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        Loop::tick(false);
    }
    
    public function testUnreference()
    {
        $timer = Timer::once($this->createCallback(0), self::TIMEOUT);
        
        $timer->unreference();
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', self::TIMEOUT);
    }
    
    /**
     * @depends testUnreference
     */
    public function testReference()
    {
        $timer = Timer::once($this->createCallback(1), self::TIMEOUT);
        
        $timer->unreference();
        $timer->reference();
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', self::TIMEOUT);
    }
    
    /**
     * @depends testUnreference
     * @depends testSet
     */
    public function testSetAfterUnreference()
    {
        $timer = Timer::once($this->createCallback(0), self::TIMEOUT);
        
        $timer->unreference();
        $timer->set(self::TIMEOUT * 2, true);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', self::TIMEOUT * 2);
    }
}
