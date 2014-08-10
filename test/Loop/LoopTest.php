<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class LoopTest extends TestCase
{
    public function testInit()
    {
        $loop = new SelectLoop();
        
        Loop::init($loop);
        
        $this->assertSame($loop, Loop::getInstance());
    }
    
    /**
     * @depends testInit
     * @expectedException Icicle\Loop\Exception\InitializedException
     */
    public function testInitAfterInitialized()
    {
        $loop = Loop::getInstance();
        
        Loop::init($loop);
    }
    
    /**
     * @depends testInit
     */
    public function testSchedule()
    {
        $callback = $this->createCallback(1);
        
        Loop::schedule($callback);
        
        Loop::tick(true);
    }
    
    /**
     * @depends testSchedule
     */
    public function testScheduleWithArgumetns()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(1, 2, 3.14, 'test');
        
        Loop::schedule($callback, 1, 2, 3.14, 'test');
        
        Loop::tick(true);
    }
    
    /**
     * @depends testSchedule
     */
    public function testScheduleWithinScheduledCallback()
    {
        $callback = function () {
            Loop::schedule($this->createCallback(1));
        };
        
        Loop::schedule($callback);
        
        Loop::tick(true);
    }
    
    /**
     * @depends testSchedule
     */
    public function testMaxScheduleDepth()
    {
        $previous = Loop::maxScheduleDepth(1);
        
        $this->assertSame(1, Loop::maxScheduleDepth());
        
        Loop::schedule($this->createCallback(1));
        Loop::schedule($this->createCallback(0));
        
        Loop::tick(true);
        
        Loop::maxScheduleDepth($previous);
        
        $this->assertSame($previous, Loop::maxScheduleDepth());
    }
    
    /**
     * @depends testInit
     */
    public function testSignalHandlingEnabled()
    {
        $this->assertSame(extension_loaded('pcntl'), Loop::signalHandlingEnabled());
    }
    
    /**
     * @depends testSchedule
     */
    public function testIsRunning()
    {
        $callback = function () {
            $this->assertTrue(Loop::isRunning());
        };
        
        Loop::schedule($callback);
        
        Loop::run();
        
        $callback = function () {
            $this->assertFalse(Loop::isRunning());
        };
        
        Loop::schedule($callback);
        
        Loop::tick(true);
    }
    
    /**
     * @depends testIsRunning
     */
    public function testStop()
    {
        $callback = function () {
            Loop::stop();
            $this->assertFalse(Loop::isRunning());
        };
        
        Loop::schedule($callback);
        
        $this->assertTrue(Loop::run());
    }
    
    /**
     * @requires extension pcntl
     * @depends testInit
     */
    public function testAddSignalHandler()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(1);
        $callback1->method('__invoke')
                  ->with($this->identicalTo(SIGUSR1));
        
        $callback2 = $this->createCallback(1);
        $callback2->method('__invoke')
                  ->with($this->identicalTo(SIGUSR2));
        
        $callback3 = $this->createCallback(1);
        
        Loop::addSignalHandler(SIGUSR1, $callback1);
        Loop::addSignalHandler(SIGUSR2, $callback2);
        Loop::addSignalHandler(SIGUSR1, $callback3);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testRemoveSignalHandler()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(2);
        $callback2 = $this->createCallback(1);
        
        Loop::addSignalHandler(SIGUSR1, $callback1);
        Loop::addSignalHandler(SIGUSR2, $callback2);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
        
        Loop::removeSignalHandler(SIGUSR2, $callback2);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
        
        Loop::removeSignalHandler(SIGUSR1, $callback1);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testRemoveAllSignalHandlersWithNoSignal()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(1);
        $callback2 = $this->createCallback(1);
        $callback3 = $this->createCallback(1);
        
        Loop::addSignalHandler(SIGUSR1, $callback1);
        Loop::addSignalHandler(SIGUSR2, $callback2);
        Loop::addSignalHandler(SIGUSR2, $callback3);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
        
        Loop::removeAllSignalHandlers();
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testRemoveAllSignalHandlersWithSignal()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(2);
        $callback2 = $this->createCallback(1);
        $callback3 = $this->createCallback(1);
        
        Loop::addSignalHandler(SIGUSR1, $callback1);
        Loop::addSignalHandler(SIGUSR2, $callback2);
        Loop::addSignalHandler(SIGUSR2, $callback3);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
        
        Loop::removeAllSignalHandlers(SIGUSR2);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop::tick(false);
    }
    
    /**
     * @depends testInit
     */
    public function testClear()
    {
        Loop::schedule($this->createCallback(0));
        
        Loop::clear();
        
        Loop::tick(false);
    }
    
    /**
     * @depends testInit
     */
    public function testReInit()
    {
        Loop::schedule($this->createCallback(1));
        
        Loop::reInit();
        
        Loop::tick(false);
    }
}
