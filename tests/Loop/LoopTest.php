<?php
namespace Icicle\Tests\Loop;

use Icicle\Loop;
use Icicle\Loop\Events\{ImmediateInterface, SignalInterface, SocketEventInterface, TimerInterface};
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class LoopTest extends TestCase
{
    const TIMEOUT = 0.1;
    const WRITE_STRING = 'abcdefghijklmnopqrstuvwxyz';
    
    public function createSockets()
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }
    
    public function testLoop()
    {
        $loop = new SelectLoop();
        
        Loop\loop($loop);
        
        $this->assertSame($loop, Loop\loop());
    }
    
    /**
     * @depends testLoop
     * @expectedException \Icicle\Loop\Exception\InitializedError
     */
    public function testLoopAfterInitialized()
    {
        $loop = Loop\loop();
        
        Loop\loop($loop);
    }

    /**
     * @depends testLoop
     */
    public function testQueue()
    {
        Loop\queue($this->createCallback(1));
        
        Loop\tick(true);
    }
    
    /**
     * @depends testQueue
     */
    public function testQueueWithArguments()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(1, 2, 3.14, 'test');
        
        Loop\queue($callback, 1, 2, 3.14, 'test');
        
        Loop\tick(true);
    }

    /**
     * @depends testQueue
     */
    public function testIsEmpty()
    {
        $this->assertTrue(Loop\isEmpty());

        Loop\queue(function () {});

        $this->assertFalse(Loop\isEmpty());

        Loop\tick(true);
    }

    public function testPoll()
    {
        list($readable, $writable) = $this->createSockets();
        
        fwrite($writable, self::WRITE_STRING);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($readable, false);
        
        $poll = Loop\poll($readable, $callback);
        
        $this->assertInstanceOf(SocketEventInterface::class, $poll);
        
        $poll->listen();
        
        Loop\run();
    }
    
    public function testAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($writable, false);
        
        $await = Loop\await($writable, $callback);
        
        $this->assertInstanceOf(SocketEventInterface::class, $await);
        
        $await->listen();
        
        Loop\run();
    }
    
    public function testTimer()
    {
        $timer = Loop\timer(self::TIMEOUT, $this->createCallback(1));
        
        $this->assertInstanceOf(TimerInterface::class, $timer);
        
        Loop\run();
    }
    
    /**
     * @depends testTimer
     */
    public function testTimerWithArguments()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(1, 2, 3.14, 'test');
        
        $timer = Loop\timer(self::TIMEOUT, $callback, 1, 2, 3.14, 'test');
        
        $this->assertInstanceOf(TimerInterface::class, $timer);
        
        Loop\tick(true);
    }
    
    public function testPeriodic()
    {
        $callback = $this->createCallback(1);
        
        $callback = function () use (&$timer, $callback) {
            $callback();
            $timer->stop();
        };
        
        $timer = Loop\periodic(self::TIMEOUT, $callback);
        
        $this->assertInstanceOf(TimerInterface::class, $timer);
        
        Loop\run();
    }
    
    /**
     * @depends testPeriodic
     */
    public function testPeriodicWithArguments()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(1, 2, 3.14, 'test');
        
        $callback = function (/* ...$args */) use (&$timer, $callback) {
            $timer->stop();
            call_user_func_array($callback, func_get_args());
        };
        
        $timer = Loop\periodic(self::TIMEOUT, $callback, 1, 2, 3.14, 'test');
        
        $this->assertInstanceOf(TimerInterface::class, $timer);
        
        Loop\run();
    }
    
    public function testImmediate()
    {
        $immediate = Loop\immediate($this->createCallback(1));
        
        $this->assertInstanceOf(ImmediateInterface::class, $immediate);
        
        Loop\run();
    }
    
    /**
     * @depends testImmediate
     */
    public function testImmediateWithArguments()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(1, 2, 3.14, 'test');
        
        $immediate = Loop\immediate($callback, 1, 2, 3.14, 'test');
        
        $this->assertInstanceOf(ImmediateInterface::class, $immediate);
        
        Loop\run();
    }
    
    /**
     * @depends testQueue
     */
    public function testQueueWithinQueuedCallback()
    {
        $callback = function () {
            Loop\queue($this->createCallback(1));
        };
        
        Loop\queue($callback);
        
        Loop\tick(true);
    }
    
    /**
     * @depends testQueue
     */
    public function testMaxQueueDepth()
    {
        $previous = Loop\maxQueueDepth(1);
        
        $this->assertSame(1, Loop\maxQueueDepth(1));
        
        Loop\queue($this->createCallback(1));
        Loop\queue($this->createCallback(0));
        
        Loop\tick(true);
        
        Loop\maxQueueDepth($previous);

        $this->assertSame($previous, Loop\maxQueueDepth($previous));
    }
    
    /**
     * @depends testLoop
     */
    public function testSignalHandlingEnabled()
    {
        $this->assertSame(extension_loaded('pcntl'), Loop\signalHandlingEnabled());
    }
    
    /**
     * @depends testQueue
     */
    public function testIsRunning()
    {
        $callback = function () {
            $this->assertTrue(Loop\isRunning());
        };
        
        Loop\queue($callback);
        
        Loop\run();
        
        $callback = function () {
            $this->assertFalse(Loop\isRunning());
        };
        
        Loop\queue($callback);
        
        Loop\tick(true);
    }
    
    /**
     * @depends testIsRunning
     */
    public function testStop()
    {
        $callback = function () {
            Loop\stop();
            $this->assertFalse(Loop\isRunning());
        };
        
        Loop\queue($callback);
        
        $this->assertTrue(Loop\run());
    }
    
    /**
     * @requires extension pcntl
     * @depends testLoop
     */
    public function testSignal()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(1);
        $callback1->method('__invoke')
                  ->with($this->identicalTo(SIGUSR1));
        
        $callback2 = $this->createCallback(1);
        $callback2->method('__invoke')
                  ->with($this->identicalTo(SIGUSR2));
        
        $callback3 = $this->createCallback(1);
        
        $signal = Loop\signal(SIGUSR1, $callback1);
        $this->assertInstanceOf(SignalInterface::class, $signal);

        $signal = Loop\signal(SIGUSR2, $callback2);
        $this->assertInstanceOf(SignalInterface::class, $signal);

        $signal = Loop\signal(SIGUSR1, $callback3);
        $this->assertInstanceOf(SignalInterface::class, $signal);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        Loop\tick(false);
    }
    
    /**
     * @depends testLoop
     */
    public function testClear()
    {
        Loop\queue($this->createCallback(0));
        
        Loop\clear();
        
        Loop\tick(false);
    }
    
    /**
     * @depends testLoop
     */
    public function testReInit()
    {
        Loop\queue($this->createCallback(1));
        
        Loop\reInit();
        
        Loop\tick(false);
    }
}
