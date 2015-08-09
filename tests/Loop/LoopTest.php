<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Loop;
use Icicle\Loop\LoopInterface;
use Icicle\Loop\Events\ImmediateInterface;
use Icicle\Loop\Events\SignalInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Tests\TestCase;

class LoopTest extends TestCase
{
    const TIMEOUT = 0.1;
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';

    /**
     * @var \Icicle\Loop\LoopInterface
     */
    protected $loop;

    public function setUp()
    {
        $this->loop = $this->getMock(LoopInterface::class);
    }
    
    public function testLoop()
    {
        Loop\loop($this->loop);
        
        $this->assertSame($this->loop, Loop\loop());
    }
    
    /**
     * @depends testLoop
     */
    public function testLoopAfterInitialized()
    {
        $this->assertNotSame($this->loop, Loop\loop());

        $this->assertSame($this->loop, Loop\loop($this->loop));
    }

    /**
     * @depends testLoop
     */
    public function testQueue()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('queue')
            ->with($this->identicalTo($callback));

        Loop\queue($callback);
    }
    
    /**
     * @depends testQueue
     */
    public function testQueueWithArguments()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('queue')
            ->with($this->identicalTo($callback), $this->identicalTo([1, 2, 3.14, 'test']));

        Loop\queue($callback, 1, 2, 3.14, 'test');
    }

    /**
     * @depends testLoop
     */
    public function testMaxQueueDepth()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('maxQueueDepth')
            ->with($this->identicalTo(1));

        Loop\maxQueueDepth(1);
    }

    /**
     * @depends testLoop
     */
    public function testIsEmpty()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('isEmpty');

        Loop\isEmpty();
    }

    /**
     * @depends testLoop
     */
    public function testPoll()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('poll')
            ->with($this->identicalTo(0), $this->identicalTo($callback))
            ->will($this->returnValue($this->getMock(SocketEventInterface::class)));

        Loop\poll(0, $callback); // No need to pass a real resource, as it is not checked here.
    }

    /**
     * @depends testLoop
     */
    public function testAwait()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('await')
            ->with($this->identicalTo(0), $this->identicalTo($callback))
            ->will($this->returnValue($this->getMock(SocketEventInterface::class)));

        Loop\await(0, $callback); // No need to pass a real resource, as it is not checked here.
    }
    
    public function testTimer()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('timer')
            ->with($this->identicalTo(self::TIMEOUT), $this->identicalTo(false), $this->identicalTo($callback))
            ->will($this->returnValue($this->getMock(TimerInterface::class)));

        $timer = Loop\timer(self::TIMEOUT, $callback);

        $this->assertInstanceOf(TimerInterface::class, $timer);
    }
    
    /**
     * @depends testTimer
     */
    public function testTimerWithArguments()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('timer')
            ->with(
                $this->identicalTo(self::TIMEOUT),
                $this->identicalTo(false),
                $this->identicalTo($callback),
                $this->identicalTo([1, 2, 3.14, 'test'])
            )
            ->will($this->returnValue($this->getMock(TimerInterface::class)));

        $timer = Loop\timer(self::TIMEOUT, $callback, 1, 2, 3.14, 'test');
        
        $this->assertInstanceOf(TimerInterface::class, $timer);
    }

    /**
     * @depends testLoop
     */
    public function testPeriodic()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('timer')
            ->with($this->identicalTo(self::TIMEOUT), $this->identicalTo(true), $this->identicalTo($callback))
            ->will($this->returnValue($this->getMock(TimerInterface::class)));

        $timer = Loop\periodic(self::TIMEOUT, $callback);

        $this->assertInstanceOf(TimerInterface::class, $timer);
    }
    
    /**
     * @depends testPeriodic
     */
    public function testPeriodicWithArguments()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('timer')
            ->with(
                $this->identicalTo(self::TIMEOUT),
                $this->identicalTo(true),
                $this->identicalTo($callback),
                $this->identicalTo([1, 2, 3.14, 'test'])
            )
            ->will($this->returnValue($this->getMock(TimerInterface::class)));

        $timer = Loop\periodic(self::TIMEOUT, $callback, 1, 2, 3.14, 'test');

        $this->assertInstanceOf(TimerInterface::class, $timer);
    }

    /**
     * @depends testLoop
     */
    public function testImmediate()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('immediate')
            ->with($this->identicalTo($callback))
            ->will($this->returnValue($this->getMock(ImmediateInterface::class)));

        $immediate = Loop\immediate($callback);

        $this->assertInstanceOf(ImmediateInterface::class, $immediate);
    }
    
    /**
     * @depends testImmediate
     */
    public function testImmediateWithArguments()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('immediate')
            ->with($this->identicalTo($callback), $this->identicalTo([1, 2, 3.14, 'test']))
            ->will($this->returnValue($this->getMock(ImmediateInterface::class)));

        $immediate = Loop\immediate($callback, 1, 2, 3.14, 'test');

        $this->assertInstanceOf(ImmediateInterface::class, $immediate);
    }

    /**
     * @depends testLoop
     */
    public function testSignalHandlingEnabled()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('signalHandlingEnabled');

        Loop\signalHandlingEnabled();
    }

    /**
     * @depends testLoop
     */
    public function testRun()
    {
        $initialize = $this->createCallback(0);

        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('run')
            ->with($this->identicalTo($initialize));

        Loop\run($initialize);
    }

    /**
     * @depends testLoop
     */
    public function testTick()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('tick')
            ->with($this->identicalTo(false));

        Loop\tick(false);
    }

    /**
     * @depends testLoop
     */
    public function testIsRunning()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('isRunning');

        Loop\isRunning();
    }
    
    /**
     * @depends testLoop
     */
    public function testStop()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('stop');

        Loop\stop();
    }

    /**
     * @depends testLoop
     */
    public function testWith()
    {
        Loop\loop($this->loop);

        $loop = $this->getMock(LoopInterface::class);

        $worker = $this->createCallback(0);

        $loop->expects($this->once())
            ->method('run')
            ->with($this->identicalTo($worker));

        Loop\with($worker, $loop);

        $this->assertSame($this->loop, Loop\loop());
    }
    
    /**
     * @depends testLoop
     */
    public function testSignal()
    {
        Loop\loop($this->loop);

        $callback = $this->createCallback(0);

        $this->loop->expects($this->once())
            ->method('signal')
            ->with($this->identicalTo(1), $this->identicalTo($callback))
            ->will($this->returnValue($this->getMock(SignalInterface::class)));

        $signal = Loop\signal(1, $callback);

        $this->assertInstanceOf(SignalInterface::class, $signal);
    }
    
    /**
     * @depends testLoop
     */
    public function testClear()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('clear');

        Loop\clear();
    }
    
    /**
     * @depends testLoop
     */
    public function testReInit()
    {
        Loop\loop($this->loop);

        $this->loop->expects($this->once())
            ->method('reInit');

        Loop\reInit();
    }
}
