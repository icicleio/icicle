<?php
namespace Icicle\Tests\Loop;

use Exception;
use Icicle\Loop\Exception\LogicException;
use Icicle\Tests\TestCase;

/**
 * Abstract class to be used as a base to test loop implementations.
 */
abstract class AbstractLoopTest extends TestCase
{
    const TIMEOUT = 1; // Should be equal to or greater than SelectLoop timeout interval.
    const RUNTIME = 0.05; // Allowed deviation from projected runtimes.
    const MICROSEC_PER_SEC = 1e6;
    const WRITE_STRING = '1234567890';
    
    protected $loop;
    
    public function setUp()
    {
        $this->loop = $this->createLoop();
    }
    
    /**
     * Creates the loop implemenation to test.
     *
     * @return  LoopInterface
     */
    abstract public function createLoop();
    
/*
    public function createSockets($timeout = self::TIMEOUT)
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        
        $readableMock = $this->getMockBuilder('Icicle\StreamSocket\Stream')
                             ->disableOriginalConstructor()
                             ->getMock();
        
        $readableMock->method('getResource')
                     ->will($this->returnValue($sockets[0]));
        
        $readableMock->method('getId')
                     ->will($this->returnValue((int) $sockets[0]));
        
        $readableMock->method('isOpen')
                     ->will($this->returnValue(true));
        
        $writableMock = $this->getMockBuilder('Icicle\StreamSocket\Stream')
                             ->disableOriginalConstructor()
                             ->getMock();
        
        $writableMock->method('getResource')
                     ->will($this->returnValue($sockets[1]));
        
        $writableMock->method('getId')
                     ->will($this->returnValue((int) $sockets[1]));
        
        $writableMock->method('isOpen')
                     ->will($this->returnValue(true));
        
        return [
            $readableMock,
            $writableMock
        ];
    }
*/
    
    public function createSockets($timeout = self::TIMEOUT)
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        
        return $sockets;
    }
    
    public function createPoll()
    {
        return $this->getMockBuilder('Icicle\Loop\Events\Poll')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
    
    public function createAwait()
    {
        return $this->getMockBuilder('Icicle\Loop\Events\Await')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
    
    public function createImmediate()
    {
        return $this->getMockBuilder('Icicle\Loop\Events\Immediate')
                    ->disableOriginalConstructor()
                    ->getMock();
    }
    
    public function createTimer($periodic = false)
    {
        $timer = $this->getMockBuilder('Icicle\Loop\Events\Timer')
                      ->disableOriginalConstructor()
                      ->getMock();
        
        $timer->method('getInterval')
              ->will($this->returnValue(self::TIMEOUT));
        
        $timer->method('isPeriodic')
              ->will($this->returnValue((bool) $periodic));
        
        return $timer;
    }
    
    public function testNoBlockingOnEmptyLoop()
    {
        $this->assertTrue($this->loop->isEmpty()); // Loop should be empty upon creation.
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME); // An empty loop should not block.
    }
    
    public function testScheduleReadableSocket()
    {
        list($socket) = $this->createSockets();
        
        $this->loop->scheduleReadableSocket($socket);
        
        $this->assertTrue($this->loop->isReadableSocketScheduled($socket));
        
        $socket->expects($this->once())
               ->method('onRead');
        
        $this->loop->tick(false); // Should call onRead()
        
        $this->assertFalse($this->loop->isReadableSocketScheduled($socket));
        
        $this->loop->tick(false); // Should not call onRead()
    }
    
    /**
     * @depends testScheduleReadableSocket
     */
    public function testRescheduleReadableSocket()
    {
        list($socket) = $this->createSockets();
        
        $this->loop->scheduleReadableSocket($socket);
        
        $this->assertTrue($this->loop->isReadableSocketScheduled($socket));
        
        $socket->expects($this->exactly(2))
               ->method('onRead');
        
        $this->loop->tick(false); // Should call onRead()
        
        $this->loop->scheduleReadableSocket($socket);
        
        $this->assertTrue($this->loop->isReadableSocketScheduled($socket));
        
        $this->loop->tick(false); // Should call onRead() again.
    }
    
    public function testScheduleWritableSocket()
    {
        list( , $socket) = $this->createSockets();
        
        $this->loop->scheduleWritableSocket($socket);
        
        $this->assertTrue($this->loop->isWritableSocketScheduled($socket));
        
        $socket->expects($this->once())
               ->method('onWrite');
        
        $this->loop->tick(false); // Should call onWrite()
        
        $this->assertFalse($this->loop->isWritableSocketScheduled($socket));
        
        $this->loop->tick(false); // Should not call onWrite()
    }
    
    /**
     * @depends testScheduleWritableSocket
     */
    public function testRescheduleWritableSocket()
    {
        list( , $socket) = $this->createSockets();
        
        $this->loop->scheduleWritableSocket($socket);
        
        $this->assertTrue($this->loop->isWritableSocketScheduled($socket));
        
        $socket->expects($this->exactly(2))
               ->method('onWrite');
        
        $this->loop->tick(false); // Should call onWrite()
        
        $this->loop->scheduleWritableSocket($socket);
        
        $this->assertTrue($this->loop->isWritableSocketScheduled($socket));
        
        $this->loop->tick(false); // Should not call onWrite()
    }
    
    /**
     * @depends testScheduleReadableSocket
     */
    public function testUnscheduleReadableSocket()
    {
        list($socket) = $this->createSockets();
        
        $this->loop->scheduleReadableSocket($socket);
        
        $this->loop->unscheduleReadableSocket($socket);
        
        $this->assertFalse($this->loop->isReadableSocketScheduled($socket));
        
        $socket->expects($this->never())
               ->method('onRead');
        
        $this->loop->tick(false); // Should not call onRead()
    }
    
    /**
     * @depends testScheduleWritableSocket
     */
    public function testUnscheduleWritableSocket()
    {
        list( , $socket) = $this->createSockets();
        
        $this->loop->scheduleWritableSocket($socket);
        
        $this->loop->unscheduleWritableSocket($socket);
        
        $this->assertFalse($this->loop->isWritableSocketScheduled($socket));
        
        $socket->expects($this->never())
               ->method('onWrite');
        
        $this->loop->tick(false); // Should not call onWrite()
    }
    
    /**
     * @depends testScheduleReadableSocket
     * @depends testScheduleWritableSocket
     */
    public function testRemoveSocket()
    {
        list($socket) = $this->createSockets();
        
        $this->loop->scheduleReadableSocket($socket);
        $this->loop->scheduleWritableSocket($socket);
        
        $this->loop->removeSocket($socket);
        
        $this->assertFalse($this->loop->isReadableSocketScheduled($socket));
        $this->assertFalse($this->loop->isWritableSocketScheduled($socket));
        
        $socket->expects($this->never())
               ->method('onRead');
        
        $socket->expects($this->never())
               ->method('onWrite');
        
        $this->loop->tick(false);
    }
    
    /**
     * @medium
     * @depends testNoBlockingOnEmptyLoop
     * @depends testScheduleReadableSocket
     */
    public function testTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $readable->method('getTimeout')
                 ->will($this->returnValue(self::TIMEOUT));
        $readable->expects($this->once())
                 ->method('onRead');
        $readable->expects($this->never())
                 ->method('onTimeout');
        
        $this->loop->scheduleReadableSocket($readable);
        
        $writable->method('getTimeout')
                 ->will($this->returnValue(self::TIMEOUT));
        $writable->expects($this->never())
                 ->method('onRead');
        $writable->expects($this->once())
                 ->method('onTimeout');
        
        $this->loop->scheduleReadableSocket($writable);
        
        $this->loop->tick(false); // Should call onRead on $readable.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false); // Should call onTimeout on $writable.
        
        $this->assertTrue($this->loop->isEmpty());
        
        list( , $socket) = $this->createSockets();
        
        $socket->method('getTimeout')
               ->will($this->returnValue(self::TIMEOUT * 2));
        $socket->expects($this->never())
               ->method('onRead');
        $socket->expects($this->once())
               ->method('onTimeout');
        
        $this->loop->scheduleReadableSocket($socket);
        
        usleep(self::TIMEOUT * 2 * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false); // Should call onTimeout.
        
        $this->assertTrue($this->loop->isEmpty());
        
        $this->loop->scheduleReadableSocket($socket);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false); // Should not call onTimeout.
        
        $this->assertFalse($this->loop->isEmpty());
    }
    
    /**
     * @medium
     * @depends testTimeout
     */
    public function testNoTimeout()
    {
        list( , $socket) = $this->createSockets();
        
        $socket->method('getTimeout')
               ->will($this->returnValue(0));
        $socket->expects($this->never())
               ->method('onTimeout');
        
        $this->loop->scheduleReadableSocket($socket);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testScheduleReadableSocket
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromReadableSocketCallback()
    {
        list($socket) = $this->createSockets();
        
        $this->loop->scheduleReadableSocket($socket);
        
        $exception = new LogicException();
        
        $socket->method('onRead')
               ->will($this->throwException($exception));
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from socket callbacks.');
    }
    
    /**
     * @depends testScheduleWritableSocket
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromWritableSocketCallback()
    {
        list( , $socket) = $this->createSockets();
        
        $this->loop->scheduleWritableSocket($socket);
        
        $exception = new LogicException();
        
        $socket->method('onWrite')
               ->will($this->throwException($exception));
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from socket callbacks.');
    }
    
    public function testSchedule()
    {
        $callback = $this->createCallback(3);
        
        $this->loop->schedule($callback);
        $this->loop->schedule($callback);
        
        $this->loop->run();
        
        $this->loop->schedule($callback);
        
        $this->loop->run();
    }
    
    /**
     * @depends testSchedule
     */
    public function testScheduleWithArguments()
    {
        $args = ['test1', 'test2', 'test3'];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($args[0]), $this->identicalTo($args[1]), $this->identicalTo($args[2]));
        
        $this->loop->schedule($callback, $args);
        
        $this->loop->run();
    }
    
    /**
     * @depends testSchedule
     */
    public function testScheduleWithinScheduledCallback()
    {
        $callback = function () {
            $this->loop->schedule($this->createCallback(1));
        };
        
        $this->loop->schedule($callback);
        
        $this->loop->run();
    }
    
    /**
     * @depends testSchedule
     */
    public function testMaxScheduleDepth()
    {
        $depth = 10;
        $ticks = 2;
        
        $previous = $this->loop->maxScheduleDepth($depth);
        
        $this->assertSame($depth, $this->loop->maxScheduleDepth());
        
        $callback = $this->createCallback($depth * $ticks);
        
        for ($i = 0; $depth * ($ticks + $ticks) > $i; ++$i) {
            $this->loop->schedule($callback);
        }
        
        for ($i = 0; $ticks > $i; ++$i) {
            $this->loop->tick(false);
        }
        
        $this->loop->maxScheduleDepth($previous);
        
        $this->assertSame($previous, $this->loop->maxScheduleDepth());
    }
    
    /**
     * @depends testSchedule
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromScheduleCallback()
    {
        $exception = new LogicException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $this->loop->schedule($callback);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from scheduled callbacks.');
    }
    
    /**
     * @depends testRunThrowsAfterThrownExceptionFromScheduleCallback
     * @expectedException Icicle\Loop\Exception\RunningException
     */
    public function testRunThrownsExceptionWhenAlreadyRunning()
    {
        $callback = function () {
            $this->loop->run();
        };
        
        $this->loop->schedule($callback);
        
        $this->loop->run();
    }
    
    /**
     * @depends testSchedule
     */
    public function testStop()
    {
        $this->loop->schedule([$this->loop, 'stop']);
        
        $this->assertSame(true, $this->loop->run());
    }
    
    public function testAddImmediate()
    {
        $immediate = $this->createImmediate();
        $immediate->expects($this->once())
                  ->method('call');
        
        $this->loop->addImmediate($immediate);
        
        $this->assertTrue($this->loop->isImmediatePending($immediate));
        
        $this->loop->tick(false);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate));
    }
    
    /**
     * @depends testAddImmediate
     */
    public function testAddSameImmediate()
    {
        $immediate = $this->createImmediate();
        $immediate->expects($this->exactly(2))
                  ->method('call');
        
        $this->loop->addImmediate($immediate);
        
        $this->loop->tick(false);
        
        $this->loop->addImmediate($immediate);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddImmediate
     */
    public function testCancelImmediate()
    {
        $immediate1 = $this->createImmediate();
        $immediate1->expects($this->never())
                   ->method('call');
        
        $immediate2 = $this->createImmediate();
        $immediate2->expects($this->once())
                   ->method('call');
        
        $this->loop->addImmediate($immediate1);
        $this->loop->addImmediate($immediate2);
        $this->loop->cancelImmediate($immediate1);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate1));
        $this->assertTrue($this->loop->isImmediatePending($immediate2));
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddImmediate
     */
    public function testOneImmediatePerTick()
    {
        $immediate1 = $this->createImmediate();
        $immediate1->expects($this->once())
                   ->method('call');
        
        $immediate2 = $this->createImmediate();
        $immediate2->expects($this->once())
                   ->method('call');
        
        $immediate3 = $this->createImmediate();
        $immediate3->expects($this->never())
                   ->method('call');
        
        $this->loop->addImmediate($immediate1);
        $this->loop->addImmediate($immediate2);
        $this->loop->addImmediate($immediate3);
        
        $this->loop->tick(false);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate1));
        $this->assertTrue($this->loop->isImmediatePending($immediate2));
        $this->assertTrue($this->loop->isImmediatePending($immediate3));
        
        $this->loop->tick(false);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate2));
        $this->assertTrue($this->loop->isImmediatePending($immediate3));
    }
    
    /**
     * @depends testAddImmediate
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromImmediateCallback()
    {
        $exception = new LogicException();
        
        $immediate = $this->createImmediate();
        $immediate->method('call')
                  ->will($this->throwException($exception));
        
        $this->loop->addImmediate($immediate);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from immediate callbacks.');
    }
    
    /**
     * @medium
     * @depends testNoBlockingOnEmptyLoop
     */
    public function testAddTimer()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $this->loop->addTimer($timer);
        
        $this->assertTrue($this->loop->isTimerPending($timer));
        
        $this->assertRunTimeBetween([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME, self::TIMEOUT + self::RUNTIME);
    }
    
    /**
     * @medium
     * @depends testAddTimer
     */
    public function testAddSameTimer()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $this->loop->addTimer($timer);
        $this->loop->addTimer($timer);
        $this->loop->addTimer($timer);
        $this->loop->addTimer($timer);
        
        $this->assertRunTimeBetween([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME, self::TIMEOUT + self::RUNTIME);
    }
    
    /**
     * @medium
     * @depends testAddTimer
     */
    public function testAddTimerAfterTimeout()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->exactly(2))
              ->method('call');
        
        $this->loop->addTimer($timer);
        
        $this->assertRunTimeBetween([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME, self::TIMEOUT + self::RUNTIME);
        
        $this->loop->addTimer($timer);
        
        $this->assertRunTimeBetween([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME, self::TIMEOUT + self::RUNTIME);
    }
    
    /**
     * @medium
     * @depends testAddTimer
     */
    public function testOverdueTimer()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $this->loop->addTimer($timer);
        
        usleep(self::TIMEOUT * 3 * self::MICROSEC_PER_SEC);
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME);
    }
    
    /**
     * @depends testNoBlockingOnEmptyLoop
     */
    public function testUnreferenceTimer()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->never())
              ->method('call');
        
        $this->loop->addTimer($timer);
        $this->loop->unreferenceTimer($timer);
        
        $this->assertTrue($this->loop->isEmpty());
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::TIMEOUT);
    }
    
    /**
     * @medium
     * @depends testUnreferenceTimer
     */
    public function testReferenceTimer()
    {
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $this->loop->addTimer($timer);
        $this->loop->unreferenceTimer($timer);
        $this->loop->referenceTimer($timer);
        
        $this->assertFalse($this->loop->isEmpty());
        
        $this->assertRunTimeGreaterThan([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME);
    }
    
    /**
     * @medium
     * @depends testAddTimer
     */
    public function testAddPeriodicTimer()
    {
        $timer = $this->createTimer(true);
        $timer->expects($this->exactly(2))
              ->method('call');
        
        $this->loop->addTimer($timer);
        
        $this->assertTrue($this->loop->isTimerPending($timer));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(true);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(true);
    }
    
    /**
     * @medium
     * @depends testAddTimer
     */
    public function testCancelTimer()
    {
        $timer = $this->createTimer(true);
        $timer->expects($this->once())
              ->method('call');
        
        $this->loop->addTimer($timer);
        
        $this->assertTrue($this->loop->isTimerPending($timer));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        $this->loop->cancelTimer($timer);
        
        $this->assertFalse($this->loop->isTimerPending($timer));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @medium
     * @depends testCancelTimer
     */
    public function testTimerWithSelfCancel()
    {
        $iterations = 3;
        
        $timer = $this->createTimer(true);
        $timer->expects($this->exactly($iterations))
              ->method('call')
              ->will($this->returnCallback(function () use ($timer, $iterations) {
                  static $count = 0;
                  ++$count;
                  if ($iterations === $count) {
                      $this->loop->cancelTimer($timer);
                  }
              }));
        
        $this->loop->addTimer($timer);
        
        $this->loop->run();
    }
    
    /**
     * @medium
     * @depends testAddTimer
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromTimerCallback()
    {
        $exception = new LogicException();
        
        $timer = $this->createTimer(false);
        $timer->method('call')
                  ->will($this->throwException($exception));
        
        $this->loop->addTimer($timer);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from timer callbacks.');
    }
    
    /**
     * @requires extension pcntl
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
        
        $this->loop->addListener(SIGUSR1, $callback1);
        $this->loop->addListener(SIGUSR2, $callback2);
        $this->loop->addListener(SIGUSR1, $callback3);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testQuitSignalWithListener()
    {
        $pid = posix_getpid();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(SIGQUIT));
        
        $this->loop->addListener(SIGQUIT, $callback);
        
        posix_kill($pid, SIGQUIT);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     * @depends testSchedule
     */
    public function testQuitSignalWithNoListeners()
    {
        $pid = posix_getpid();
        
        $this->loop->maxScheduleDepth(1);
        
        $callback = function () use (&$callback, $pid) {
            posix_kill($pid, SIGQUIT);
            $this->loop->schedule($callback);
        };
        
        $this->loop->schedule($callback);
        
        $this->assertSame(0, $this->loop->getListenerCount(SIGQUIT));
        
        $this->assertSame(true, $this->loop->run());
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testTerminateSignal()
    {
        $pid = posix_getpid();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(SIGTERM));
        
        $this->loop->addListener(SIGTERM, $callback);
        
        $this->loop->maxScheduleDepth(1);
        
        $callback = function () use (&$callback, $pid) {
            posix_kill($pid, SIGTERM);
            $this->loop->schedule($callback);
        };
        
        $this->loop->schedule($callback);
        
        $this->assertSame(true, $this->loop->run());
    }
    
    public function testChildSignal()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(SIGCHLD));
        
        $this->loop->addListener(SIGCHLD, $callback);
        
        $fd = [
            ['pipe', 'r'], // stdin
            ['pipe', 'w'], // stdout
            ['pipe', 'w'], // stderr
        ];
        
        proc_open('whoami', $fd, $pipes);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testRemoveSignalHandler()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(2);
        $callback2 = $this->createCallback(1);
        
        $this->loop->addListener(SIGUSR1, $callback1);
        $this->loop->addListener(SIGUSR2, $callback2);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
        
        $this->loop->removeListener(SIGUSR2, $callback2);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
        
        $this->loop->removeListener(SIGUSR1, $callback1);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testRemoveAllSignalHandlers()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(1);
        $callback2 = $this->createCallback(1);
        $callback3 = $this->createCallback(1);
        
        $this->loop->addListener(SIGUSR1, $callback1);
        $this->loop->addListener(SIGUSR2, $callback2);
        $this->loop->addListener(SIGUSR2, $callback3);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
        
        $this->loop->removeAllListeners();
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testAddSignalHandler
     */
    public function testRemoveAllSignalHandlersFromSpecificSignal()
    {
        $pid = posix_getpid();
        
        $callback1 = $this->createCallback(2);
        $callback2 = $this->createCallback(1);
        $callback3 = $this->createCallback(1);
        
        $this->loop->addListener(SIGUSR1, $callback1);
        $this->loop->addListener(SIGUSR2, $callback2);
        $this->loop->addListener(SIGUSR2, $callback3);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
        
        $this->loop->removeAllListeners(SIGUSR2);
        
        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);
        
        $this->loop->tick(false);
    }
    
    /**
     * @medium
     * @depends testScheduleReadableSocket
     * @depends testScheduleWritableSocket
     * @depends testAddImmediate
     * @depends testAddTimer
     */
    public function testIsEmpty()
    {
        list($readable, $writable) = $this->createSockets();
        
        $readable->expects($this->once())
                 ->method('onRead');
        
        $writable->expects($this->once())
                 ->method('onWrite');
        
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $immediate = $this->createImmediate();
        $immediate->expects($this->once())
                  ->method('call');
        
        $this->loop->scheduleReadableSocket($readable);
        $this->loop->scheduleWritableSocket($writable);
        $this->loop->addTimer($timer);
        $this->loop->addImmediate($immediate);
        $this->loop->schedule($this->createCallback(1));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        $this->assertTrue($this->loop->isEmpty());
    }
    
    /**
     * @medium
     * @depends testScheduleReadableSocket
     * @depends testScheduleWritableSocket
     * @depends testAddImmediate
     * @depends testAddTimer
     */
    public function testClear()
    {
        list($readable, $writable) = $this->createSockets();
        
        $timer = $this->createTimer(false);
        $timer->expects($this->never())
              ->method('call');
        
        $immediate = $this->createImmediate();
        $immediate->expects($this->never())
                  ->method('call');
        
        $this->loop->scheduleReadableSocket($readable);
        $this->loop->scheduleWritableSocket($writable);
        $this->loop->addTimer($timer);
        $this->loop->addImmediate($immediate);
        $this->loop->schedule($this->createCallback(0));
        
        $this->loop->clear();
        
        $this->assertTrue($this->loop->isEmpty());
        
        $this->assertFalse($this->loop->isReadableSocketScheduled($readable));
        $this->assertFalse($this->loop->isWritableSocketScheduled($writable));
        $this->assertFalse($this->loop->isTimerPending($timer));
        $this->assertFalse($this->loop->isImmediatePending($immediate));
        
        $this->loop->scheduleReadableSocket($readable);
        $this->loop->scheduleWritableSocket($writable);
        
        $readable->expects($this->once())
                 ->method('onRead');
        
        $writable->expects($this->once())
                 ->method('onWrite');
        
        $this->loop->tick(false);
    }
    
    /**
     * @medium
     * @depends testScheduleReadableSocket
     * @depends testScheduleWritableSocket
     * @depends testAddImmediate
     * @depends testAddTimer
     */
    public function testReInit()
    {
        list($readable, $writable) = $this->createSockets();
        
        $readable->expects($this->once())
                 ->method('onRead');
        
        $writable->expects($this->once())
                 ->method('onWrite');
        
        $timer = $this->createTimer(false);
        $timer->expects($this->once())
              ->method('call');
        
        $immediate = $this->createImmediate();
        $immediate->expects($this->once())
                  ->method('call');
        
        $this->loop->scheduleReadableSocket($readable);
        $this->loop->scheduleWritableSocket($writable);
        $this->loop->addTimer($timer);
        $this->loop->addImmediate($immediate);
        $this->loop->schedule($this->createCallback(1));
        
        $this->loop->reInit();
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
}
