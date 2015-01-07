<?php
namespace Icicle\Tests\Loop;

use Exception;
use Icicle\Loop\Events\EventFactory;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\LogicException;
use Icicle\Tests\TestCase;

/**
 * Abstract class to be used as a base to test loop implementations.
 */
abstract class AbstractLoopTest extends TestCase
{
    const TIMEOUT = 0.1;
    const RUNTIME = 0.05; // Allowed deviation from projected runtimes.
    const MICROSEC_PER_SEC = 1e6;
    const WRITE_STRING = '1234567890';
    const RESOURCE = 1;
    const CHUNK_SIZE = 8192;
    
    protected $loop;
    
    public function setUp()
    {
        $this->loop = $this->createLoop($this->createEventFactory());
    }
    
    /**
     * Creates the loop implemenation to test.
     *
     * @return  LoopInterface
     */
    abstract public function createLoop(EventFactoryInterface $eventFactory);
    
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
    
    public function createEventFactory()
    {
        return new EventFactory();
/*
        $factory = $this->getMockBuilder('Icicle\Loop\Events\EventFactory')
                     ->getMock();
        
        $factory->method('createPoll')
                ->will($this->returnValue($this->createPoll()));
        
        $factory->method('createAwait')
                ->will($this->returnValue($this->createAwait()));
        
        $factory->method('createImmediate')
                ->will($this->returnValue($this->createImmediate()));
        
        $factory->method('createTimer')
                ->will($this->returnValue($this->createTimer()));
        
        return $factory;
*/
    }
    
    public function createSockets($timeout = self::TIMEOUT)
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.
        
        return $sockets;
    }
    
    public function createPoll()
    {
        $poll = $this->getMockBuilder('Icicle\Loop\Events\Poll')
                     ->disableOriginalConstructor()
                     ->getMock();
        
        $poll->method('getCallback')
             ->will($this->returnValue($this->createEmptyCallback()));
        
        return $poll;
    }
    
    public function createAwait()
    {
        $await = $this->getMockBuilder('Icicle\Loop\Events\Await')
                      ->disableOriginalConstructor()
                      ->getMock();
        
        $await->method('getCallback')
              ->will($this->returnValue($this->createEmptyCallback()));
        
        return $await;
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
    
    public function testCreatePoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->assertInstanceOf('Icicle\Loop\Events\PollInterface', $poll);
    }
    
    /**
     * @depends testCreatePoll
     */
    public function testListenPoll()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $poll = $this->loop->createPoll($socket, $callback);
        
        $this->loop->listenPoll($poll);
        
        $this->assertTrue($this->loop->isPollPending($poll));
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenPoll
     */
    public function testCancelPoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->loop->listenPoll($poll);
        
        $this->loop->cancelPoll($poll);
        
        $this->assertFalse($this->loop->isPollPending($poll));
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenPoll
     */
    public function testRelistenPoll()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $poll = $this->loop->createPoll($socket, $callback);
        
        $this->loop->listenPoll($poll);
        
        $this->assertTrue($this->loop->isPollPending($poll));
        
        $this->loop->tick(false); // Should invoke callback.
        
        $this->loop->listenPoll($poll);
        
        $this->assertTrue($this->loop->isPollPending($poll));
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenPoll
     */
    public function testListenPollWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($writable), $this->identicalTo(true));
        
        $poll = $this->loop->createPoll($writable, $callback);
        
        $this->loop->listenPoll($poll, self::TIMEOUT);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testListenPoll
     * @expectedException Icicle\Loop\Exception\FreedException
     */
    public function testFreePoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->assertFalse($this->loop->isPollFreed($poll));
        
        $this->loop->freePoll($poll);
        
        $this->assertTrue($this->loop->isPollFreed($poll));
        
        $this->loop->listenPoll($poll);
    }
    
    public function testCreateAwait()
    {
        list( , $socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->assertInstanceOf('Icicle\Loop\Events\AwaitInterface', $await);
    }
    
    /**
     * @depends testCreateAwait
     */
    public function testListenAwait()
    {
        list( , $socket) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $await = $this->loop->createAwait($socket, $callback);
        
        $this->loop->listenAwait($await);
        
        $this->assertTrue($this->loop->isAwaitPending($await));
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenAwait
     */
    public function testRelistenAwait()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $await = $this->loop->createAwait($socket, $callback);
        
        $this->loop->listenAwait($await);
        
        $this->assertTrue($this->loop->isAwaitPending($await));
        
        $this->loop->tick(false); // Should invoke callback.
        
        $this->loop->listenAwait($await);
        
        $this->assertTrue($this->loop->isAwaitPending($await));
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenPoll
     */
/*
    public function testListenAwaitWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $length = strlen(self::WRITE_STRING);
        
        //while (0 < fwrite($writable, self::WRITE_STRING, self::CHUNK_SIZE));
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
                 ->with($this->identicalTo($writable), $this->identicalTo(true));
        
        $await = $this->loop->createAwait($writable, $callback);
        
        $this->loop->listenAwait($await);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        $this->loop->listenAwait($await, self::TIMEOUT);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
*/
    
    /**
     * @depends testListenAwait
     * @expectedException Icicle\Loop\Exception\FreedException
     */
    public function testFreeAwait()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->assertFalse($this->loop->isAwaitFreed($await));
        
        $this->loop->freeAwait($await);
        
        $this->assertTrue($this->loop->isAwaitFreed($await));
        
        $this->loop->listenAwait($await);
    }
    
    /**
     * @depends testListenPoll
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromPollCallback()
    {
        list($socket) = $this->createSockets();
        
        $exception = new LogicException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $poll = $this->loop->createPoll($socket, $callback);
        
        $this->loop->listenPoll($poll);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from poll callbacks.');
    }    
    
    /**
     * @depends testListenAwait
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromAwaitCallback()
    {
        list( , $socket) = $this->createSockets();
        
        $exception = new LogicException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $await = $this->loop->createAwait($socket, $callback);
        
        $this->loop->listenAwait($await);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from await callbacks.');
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
    
    public function testCreateImmediate()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(1));
        
        $this->assertInstanceOf('Icicle\Loop\Events\ImmediateInterface', $immediate);
        
        $this->assertTrue($this->loop->isImmediatePending($immediate));
        
        $this->loop->tick(false); // Should invoke immediate callback.
    }
    
    /**
     * @depends testCreateImmediate
     */
    public function testCancelImmediate()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(0));
        
        $this->loop->cancelImmediate($immediate);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate));
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testCreateImmediate
     */
    public function testOneImmediatePerTick()
    {
        $immediate1 = $this->loop->createImmediate($this->createCallback(1));
        $immediate2 = $this->loop->createImmediate($this->createCallback(1));
        $immediate3 = $this->loop->createImmediate($this->createCallback(0));
        
        $this->loop->tick(false);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate1));
        $this->assertTrue($this->loop->isImmediatePending($immediate2));
        $this->assertTrue($this->loop->isImmediatePending($immediate3));
        
        $this->loop->tick(false);
        
        $this->assertFalse($this->loop->isImmediatePending($immediate2));
        $this->assertTrue($this->loop->isImmediatePending($immediate3));
    }
    
    /**
     * @depends testCreateImmediate
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromImmediateCallback()
    {
        $exception = new LogicException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $immediate = $this->loop->createImmediate($callback);
        
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
     * @depends testNoBlockingOnEmptyLoop
     */
    public function testCreateTimer()
    {
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT, false);
        
        $this->assertTrue($this->loop->isTimerPending($timer));
        
        $this->assertRunTimeBetween([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME, self::TIMEOUT + self::RUNTIME);
    }
    
    /**
     * @depends testCreateTimer
     */
    public function testOverdueTimer()
    {
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT, false);
        
        usleep(self::TIMEOUT * 3 * self::MICROSEC_PER_SEC);
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME);
    }
    
    /**
     * @depends testNoBlockingOnEmptyLoop
     */
    public function testUnreferenceTimer()
    {
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT, false);
        
        $this->loop->unreferenceTimer($timer);
        
        $this->assertTrue($this->loop->isEmpty());
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::TIMEOUT);
    }
    
    /**
     * @depends testUnreferenceTimer
     */
    public function testReferenceTimer()
    {
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT, false);
        
        $this->loop->unreferenceTimer($timer);
        $this->loop->referenceTimer($timer);
        
        $this->assertFalse($this->loop->isEmpty());
        
        $this->assertRunTimeGreaterThan([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME);
    }
    
    /**
     * @depends testCreateTimer
     */
    public function testCreatePeriodicTimer()
    {
        $timer = $this->loop->createTimer($this->createCallback(2), self::TIMEOUT, true);
        
        $this->assertTrue($this->loop->isTimerPending($timer));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(true);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(true);
    }
    
    /**
     * @depends testCreatePeriodicTimer
     */
    public function testCancelTimer()
    {
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT, true);
        
        $this->assertTrue($this->loop->isTimerPending($timer));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        $this->loop->cancelTimer($timer);
        
        $this->assertFalse($this->loop->isTimerPending($timer));
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testCancelTimer
     * @depends testCreatePeriodicTimer
     */
    public function testTimerWithSelfCancel()
    {
        $iterations = 3;
        
        $callback = $this->createCallback($iterations);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function () use (&$timer, $iterations) {
                     static $count = 0;
                     ++$count;
                     if ($iterations === $count) {
                         $this->loop->cancelTimer($timer);
                    }
                 }));
        
        $timer = $this->loop->createTimer($callback, self::TIMEOUT, true);
        
        $this->loop->run();
    }
    
    /**
     * @medium
     * @depends testCreateTimer
     * @expectedException Icicle\Loop\Exception\LogicException
     */
    public function testRunThrowsAfterThrownExceptionFromTimerCallback()
    {
        $exception = new LogicException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $timer = $this->loop->createTimer($callback, self::TIMEOUT, false);
        
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
     * @depends testCreatePoll
     * @depends testCreateAwait
     * @depends testCreateImmediate
     * @depends testCreateTimer
     * @depends testSchedule
     */
    public function testIsEmpty()
    {
        list($readable, $writable) = $this->createSockets();
        
        $poll = $this->loop->createPoll($readable, $this->createCallback(1));
        $await = $this->loop->createAwait($writable, $this->createCallback(1));
        $immediate = $this->loop->createImmediate($this->createCallback(1));
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT, false);
        
        $this->loop->schedule($this->createCallback(1));
        
        $this->loop->listenPoll($poll);
        $this->loop->listenAwait($await);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        $this->assertTrue($this->loop->isEmpty());
    }
    
    /**
     * @depends testCreatePoll
     * @depends testCreateAwait
     * @depends testCreateImmediate
     * @depends testCreatePeriodicTimer
     * @depends testSchedule
     */
    public function testClear()
    {
        list($readable, $writable) = $this->createSockets();
        
        $poll = $this->loop->createPoll($readable, $this->createCallback(0));
        $await = $this->loop->createAwait($readable, $this->createCallback(0));
        $immediate = $this->loop->createImmediate($this->createCallback(0));
        $timer = $this->loop->createTimer($this->createCallback(0), self::TIMEOUT, true);
        
        $this->loop->schedule($this->createCallback(0));
        
        $this->loop->clear();
        
        $this->assertTrue($this->loop->isEmpty());
        
        $this->assertFalse($this->loop->isPollPending($poll));
        $this->assertFalse($this->loop->isAwaitPending($await));
        $this->assertFalse($this->loop->isTimerPending($timer));
        $this->assertFalse($this->loop->isImmediatePending($immediate));
        
        $this->assertTrue($this->loop->isPollFreed($poll));
        $this->assertTrue($this->loop->isAwaitFreed($await));
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testCreatePoll
     * @depends testCreateAwait
     * @depends testCreateImmediate
     * @depends testCreatePeriodicTimer
     * @depends testSchedule
     */
    public function testReInit()
    {
        list($readable, $writable) = $this->createSockets();
        
        $poll = $this->loop->createPoll($readable, $this->createCallback(1));
        $await = $this->loop->createAwait($writable, $this->createCallback(1));
        $immediate = $this->loop->createImmediate($this->createCallback(1));
        $timer = $this->loop->createTimer($this->createCallback(1), self::TIMEOUT, false);
        
        $this->loop->schedule($this->createCallback(1));
        
        $this->loop->listenPoll($poll);
        $this->loop->listenAwait($await);
        
        $this->loop->reInit(); // Calling this function should not cancel any pending events.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
}
