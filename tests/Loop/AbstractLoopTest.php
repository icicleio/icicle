<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop;

use Icicle\Exception\UnsupportedError;
use Icicle\Loop\Watcher\{Immediate, Io, Signal, Timer};
use Icicle\Loop\Loop;
use Icicle\Tests\TestCase;
use Throwable;

class LoopTestException extends \Exception {}

/**
 * Abstract class to be used as a base to test loop implementations.
 */
abstract class AbstractLoopTest extends TestCase
{
    const TIMEOUT = 0.3;
    const RUNTIME = 0.1; // Allowed deviation from projected run times.
    const MICROSEC_PER_SEC = 1e6;
    const WRITE_STRING = '1234567890';
    const RESOURCE = 1;
    const CHUNK_SIZE = 8192;

    /**
     * @var \Icicle\Loop\Loop
     */
    protected $loop;
    
    public function setUp()
    {
        $this->loop = $this->createLoop();
    }
    
    /**
     * Creates the loop implementation to test.
     *
     * @return Loop
     */
    abstract public function createLoop();

    public function createSockets()
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($sockets[1], self::WRITE_STRING); // Make $sockets[0] readable.

        return $sockets;
    }
    
    public function testNoBlockingOnEmptyLoop()
    {
        $this->assertTrue($this->loop->isEmpty()); // Loop should be empty upon creation.
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME); // An empty loop should not block.
    }
    
    public function testCreatePoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
        
        $this->assertInstanceOf(Io::class, $poll);
    }
    
    /**
     * @depends testCreatePoll
     * @expectedException \Icicle\Loop\Exception\ResourceBusyError
     */
    public function testDoublePoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
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
        
        $poll = $this->loop->poll($socket, $callback);
        
        $poll->listen();
        
        $this->assertTrue($poll->isPending());
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenPoll
     */
    public function testCancelPoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
        
        $poll->listen();
        
        $poll->cancel();
        
        $this->assertFalse($poll->isPending());
        
        $this->loop->tick(false); // Should not invoke callback.
        
        $this->assertFalse($poll->isPending());
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
        
        $poll = $this->loop->poll($socket, $callback);
        
        $poll->listen();
        
        $this->assertTrue($poll->isPending());
        
        $this->loop->tick(false); // Should invoke callback.
        
        $poll->listen();
        
        $this->assertTrue($poll->isPending());
        
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
            ->with($this->identicalTo($readable), $this->identicalTo(false));
        
        $poll = $this->loop->poll($readable, $callback);
        
        $poll->listen(self::TIMEOUT);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testListenPollWithTimeout
     */
    public function testListenPollWithExpiredTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(true));
        
        $poll = $this->loop->poll($writable, $callback);
        
        $poll->listen(self::TIMEOUT);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testListenPollWithTimeout
     */
    public function testListenPollWithInvalidTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(true));
        
        $poll = $this->loop->poll($writable, $callback);
        
        $poll->listen(-1);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }

    /**
     * @depends testListenPollWithTimeout
     */
    public function testDoubleListenPollWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();

        $callback = $this->createCallback(0);

        $poll = $this->loop->poll($writable, $callback);

        $poll->listen(self::TIMEOUT);
        $poll->listen(self::TIMEOUT * 2);

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);

        $this->loop->tick(false);
    }
    
    /**
     * @depends testListenPollWithTimeout
     */
    public function testCancelPollWithTimeout()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
        
        $poll->listen(self::TIMEOUT);
        
        $poll->cancel($poll);
        
        $this->assertFalse($poll->isPending());
        
        $this->loop->tick(false); // Should not invoke callback.
        
        $this->assertFalse($poll->isPending());
    }
    
    /**
     * @depends testListenPoll
     */
    public function testPersistentPoll()
    {
        list($readable, $writable) = $this->createSockets();

        $callback = $this->createCallback(2);

        $callback->method('__invoke')
            ->with($this->identicalTo($readable), $this->identicalTo(false));

        $poll = $this->loop->poll($readable, $callback, true);

        $poll->listen();

        $this->assertTrue($poll->isPending());

        $this->loop->tick(false); // Should invoke callback.

        $this->assertTrue($poll->isPending());

        fwrite($writable, self::WRITE_STRING);

        $this->loop->tick(false); // Should invoke callback again.
    }

    /**
     * @depends testPersistentPoll
     */
    public function testPersistentPollWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();

        $callback = $this->createCallback(2);

        $callback->method('__invoke')
            ->with($this->identicalTo($readable), $this->identicalTo(false));

        $poll = $this->loop->poll($readable, $callback, true);

        $poll->listen(self::TIMEOUT);

        $this->loop->tick(false);

        $this->assertTrue($poll->isPending());

        fwrite($writable, self::WRITE_STRING);

        $this->loop->tick(false); // Should invoke callback again.
    }

    /**
     * @depends testPersistentPoll
     */
    public function testPersistentPollWithExpiredTimeout()
    {
        list($readable, $writable) = $this->createSockets();

        $callback = $this->createCallback(2);

        $callback->expects($this->at(0))
            ->method('__invoke')
            ->with($this->identicalTo($readable), $this->identicalTo(false));

        $callback->expects($this->at(1))
            ->method('__invoke')
            ->with($this->identicalTo($readable), $this->identicalTo(true));

        $poll = $this->loop->poll($readable, $callback, true);

        $poll->listen(self::TIMEOUT);

        $this->loop->tick(false);

        fread($readable, self::CHUNK_SIZE); // Drain readable stream.

        $this->assertTrue($poll->isPending());

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);

        $this->loop->tick(false); // Poll should timeout.

        $this->assertFalse($poll->isPending());
    }

    /**
     * @depends testPersistentPollWithExpiredTimeout
     */
    public function testPersistentPollRelistenWithoutTimeout()
    {
        list($readable, $writable) = $this->createSockets();

        $poll = $this->loop->poll($writable, $this->createCallback(0), true);

        $poll->listen(self::TIMEOUT);

        $this->loop->tick(false);

        $this->assertTrue($poll->isPending());

        $poll->listen();

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);

        $this->loop->tick(false); // Poll should not timeout.

        $this->assertTrue($poll->isPending());
    }

    /**
     * @depends testListenPoll
     * @expectedException \Icicle\Loop\Exception\FreedError
     */
    public function testFreePoll()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
        
        $poll->listen();
        
        $this->assertFalse($poll->isFreed());
        
        $poll->free();
        
        $this->assertTrue($poll->isFreed());
        $this->assertFalse($poll->isPending());
        
        $poll->listen();
    }
    
    /**
     * @depends testFreePoll
     * @expectedException \Icicle\Loop\Exception\FreedError
     */
    public function testFreePollWithTimeout()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->poll($socket, $this->createCallback(0));
        
        $poll->listen(self::TIMEOUT);
        
        $this->assertFalse($poll->isFreed());
        
        $poll->free();
        
        $this->assertTrue($poll->isFreed());
        $this->assertFalse($poll->isPending());
        
        $poll->listen(self::TIMEOUT);
    }

    public function testUnreferencePoll()
    {
        list($readable, $writable) = $this->createSockets();

        $poll = $this->loop->poll($writable, $this->createCallback(0));
        $poll->listen();

        $poll->unreference();

        $this->assertTrue($this->loop->isEmpty());

        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME);

        $poll->reference();

        $this->assertFalse($this->loop->isEmpty());
    }
    
    public function testCreateAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $await = $this->loop->await($writable, $this->createCallback(0));
        
        $this->assertInstanceOf(Io::class, $await);
    }
    
    /**
     * @depends testCreateAwait
     * @expectedException \Icicle\Loop\Exception\ResourceBusyError
     */
    public function testDoubleAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $await = $this->loop->await($writable, $this->createCallback(0));
        
        $await = $this->loop->await($writable, $this->createCallback(0));
    }
    
    /**
     * @depends testCreateAwait
     */
    public function testListenAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(false));
        
        $await = $this->loop->await($writable, $callback);
        
        $await->listen();
        
        $this->assertTrue($await->isPending());
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenAwait
     */
    public function testRelistenAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        
        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(false));
        
        $await = $this->loop->await($writable, $callback);
        
        $await->listen();
        
        $this->assertTrue($await->isPending());
        
        $this->loop->tick(false); // Should invoke callback.
        
        $await->listen();
        
        $this->assertTrue($await->isPending());
        
        $this->loop->tick(false); // Should invoke callback.
    }
    
    /**
     * @depends testListenAwait
     */
    public function testCancelAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $await = $this->loop->await($writable, $this->createCallback(0));
        
        $await->listen();
        
        $await->cancel();
        
        $this->assertFalse($await->isPending());
        
        $this->loop->tick(false); // Should not invoke callback.
        
        $this->assertFalse($await->isFreed());
    }
    
    /**
     * @depends testListenAwait
     */
    public function testListenAwaitWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(false));
        
        $await = $this->loop->await($writable, $callback);
        
        $await->listen(self::TIMEOUT);
        
        $this->loop->tick(false);
    }

    /**
     * @depends testListenAwaitWithTimeout
     */
    public function testListenAwaitWithInvalidTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        
        $await = $this->loop->await($writable, $callback);
        
        $await->listen(-1);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testCancelAwait
     */
    public function testCancelAwaitWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $await = $this->loop->await($writable, $this->createCallback(0));
        
        $await->listen(self::TIMEOUT);
        
        $await->cancel();
        
        $this->assertFalse($await->isPending());
        
        $this->loop->tick(false); // Should not invoke callback.
        
        $this->assertFalse($await->isFreed());
    }

    /**
     * @depends testListenAwait
     */
    public function testPersistentAwait()
    {
        list($readable, $writable) = $this->createSockets();

        $callback = $this->createCallback(2);

        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(false));

        $await = $this->loop->await($writable, $callback, true);

        $await->listen();

        $this->assertTrue($await->isPending());

        $this->loop->tick(false); // Should invoke callback.

        $this->assertTrue($await->isPending());

        $this->loop->tick(false); // Should invoke callback again.
    }

    /**
     * @depends testPersistentAwait
     */
    public function testPersistentAwaitWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();

        $callback = $this->createCallback(2);

        $callback->method('__invoke')
            ->with($this->identicalTo($writable), $this->identicalTo(false));

        $await = $this->loop->await($writable, $callback, true);

        $await->listen(self::TIMEOUT);

        $this->assertTrue($await->isPending());

        $this->loop->tick(false); // Should invoke callback.

        $this->assertTrue($await->isPending());

        $this->loop->tick(false); // Should invoke callback again.
    }

    /**
     * @depends testListenAwait
     * @expectedException \Icicle\Loop\Exception\FreedError
     */
    public function testFreeAwait()
    {
        list($readable, $writable) = $this->createSockets();
        
        $await = $this->loop->await($writable, $this->createCallback(0));
        
        $await->listen();
        
        $this->assertFalse($await->isFreed());
        
        $await->free();
        
        $this->assertTrue($await->isFreed());
        $this->assertFalse($await->isPending());
        
        $await->listen();
    }
    
    /**
     * @depends testFreeAwait
     * @expectedException \Icicle\Loop\Exception\FreedError
     */
    public function testFreeAwaitWithTimeout()
    {
        list($readable, $writable) = $this->createSockets();
        
        $await = $this->loop->await($writable, $this->createCallback(0));
        
        $await->listen(self::TIMEOUT);
        
        $this->assertFalse($await->isFreed());
        
        $await->free();
        
        $this->assertTrue($await->isFreed());
        $this->assertFalse($await->isPending());
        
        $await->listen(self::TIMEOUT);
    }

    public function testUnreferenceAwait()
    {
        list($readable, $writable) = $this->createSockets();

        $await = $this->loop->await($writable, $this->createCallback(0));
        $await->listen();

        $await->unreference();

        $this->assertTrue($this->loop->isEmpty());

        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME);

        $await->reference();

        $this->assertFalse($this->loop->isEmpty());
    }

    /**
     * @depends testListenPoll
     * @expectedException \Icicle\Tests\Loop\LoopTestException
     */
    public function testRunThrowsAfterThrownExceptionFromPollCallback()
    {
        list($socket) = $this->createSockets();

        $exception = new LoopTestException();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));

        $poll = $this->loop->poll($socket, $callback);

        $poll->listen();

        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Throwable $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }

        $this->fail('Loop should not catch exceptions thrown from poll callbacks.');
    }

    /**
     * @depends testListenAwait
     * @expectedException \Icicle\Tests\Loop\LoopTestException
     */
    public function testRunThrowsAfterThrownExceptionFromAwaitCallback()
    {
        list($readable, $writable) = $this->createSockets();

        $exception = new LoopTestException();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));

        $await = $this->loop->await($writable, $callback);

        $await->listen();

        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Throwable $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }

        $this->fail('Loop should not catch exceptions thrown from await callbacks.');
    }
    
    public function testQueue()
    {
        $callback = $this->createCallback(3);
        
        $this->loop->queue($callback);
        $this->loop->queue($callback);
        
        $this->loop->run();
        
        $this->loop->queue($callback);
        
        $this->loop->run();
    }
    
    /**
     * @depends testQueue
     */
    public function testQueueWithArguments()
    {
        $args = ['test1', 'test2', 'test3'];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($args[0]), $this->identicalTo($args[1]), $this->identicalTo($args[2]));

        $this->loop->queue($callback, $args);
        
        $this->loop->run();
    }
    
    /**
     * @depends testQueue
     */
    public function testQueueWithinQueuedCallback()
    {
        $callback = function () {
            $this->loop->queue($this->createCallback(1));
        };
        
        $this->loop->queue($callback);
        
        $this->loop->run();
    }
    
    /**
     * @depends testQueue
     */
    public function testMaxQueueDepth()
    {
        $depth = 10;
        $ticks = 2;
        
        $previous = $this->loop->maxQueueDepth($depth);
        
        $this->assertSame($depth, $this->loop->maxQueueDepth($depth));
        
        $callback = $this->createCallback($depth * $ticks);
        
        for ($i = 0; $depth * ($ticks + $ticks) > $i; ++$i) {
            $this->loop->queue($callback);
        }
        
        for ($i = 0; $ticks > $i; ++$i) {
            $this->loop->tick(false);
        }
        
        $this->loop->maxQueueDepth($previous);
        
        $this->assertSame($previous, $this->loop->maxQueueDepth($previous));
    }
    
    /**
     * @depends testQueue
     * @expectedException \Icicle\Tests\Loop\LoopTestException
     */
    public function testRunThrowsAfterThrownExceptionFromQueueCallback()
    {
        $exception = new LoopTestException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $this->loop->queue($callback);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Throwable $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from queued callbacks.');
    }
    
    /**
     * @depends testRunThrowsAfterThrownExceptionFromQueueCallback
     * @expectedException \Icicle\Loop\Exception\RunningError
     */
    public function testRunThrowsExceptionWhenAlreadyRunning()
    {
        $callback = function () {
            $this->loop->run();
        };
        
        $this->loop->queue($callback);
        
        $this->loop->run();
    }

    /**
     * @depends testQueue
     */
    public function testRunCallsInitializeFunctionImmediately()
    {
        $invoked = false;

        $this->loop->queue(function () use (&$invoked) {
            $this->assertTrue($invoked);
        });

        $this->loop->run(function () use (&$invoked) {
            $invoked = true;
        });
    }

    /**
     * @depends testRunCallsInitializeFunctionImmediately
     * @expectedException \Icicle\Tests\Loop\LoopTestException
     */
    public function testInitializeFunctionThrowingStopsLoopAndThrowsFromRun()
    {
        $exception = new LoopTestException();

        try {
            $this->loop->run(function () use ($exception) {
                throw $exception;
            });
        } catch (Exception $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }

        $this->fail('Exceptions thrown from initialize function should be thrown from run().');
    }
    
    /**
     * @depends testQueue
     */
    public function testStop()
    {
        $this->loop->queue([$this->loop, 'stop']);

        $this->assertFalse($this->loop->run());

        $this->loop->queue([$this->loop, 'stop']);
        $this->loop->timer(self::TIMEOUT, false, $this->createCallback(0));

        $this->assertTrue($this->loop->run());
    }
    
    public function testCreateImmediate()
    {
        $immediate = $this->loop->immediate($this->createCallback(1));
        
        $this->assertInstanceOf(Immediate::class, $immediate);
        
        $this->assertTrue($immediate->isPending());
        
        $this->loop->run();
    }

    /**
     * @depends testCreateImmediate
     */
    public function testImmediateInvokedOnlyOnBlocking()
    {
        $immediate = $this->loop->immediate($this->createCallback(1));

        $this->loop->tick(false);

        $this->assertTrue($immediate->isPending());

        $this->loop->tick(true);

        $this->assertFalse($immediate->isPending());
    }

    /**
     * @depends testImmediateInvokedOnlyOnBlocking
     */
    public function testOneImmediatePerTick()
    {
        $immediate1 = $this->loop->immediate($this->createCallback(1));
        $immediate2 = $this->loop->immediate($this->createCallback(1));
        $immediate3 = $this->loop->immediate($this->createCallback(0));
        
        $this->loop->tick(true);
        
        $this->assertFalse($immediate1->isPending());
        $this->assertTrue($immediate2->isPending());
        $this->assertTrue($immediate3->isPending());
        
        $this->loop->tick(true);
        
        $this->assertFalse($immediate2->isPending());
        $this->assertTrue($immediate3->isPending());
    }

    /**
     * @depends testCreateImmediate
     */
    public function testExecuteImmediate()
    {
        $immediate = $this->loop->immediate($this->createCallback(3));

        $this->loop->run();

        $immediate->execute();

        $this->loop->run();

        $immediate->execute();

        $this->loop->run();
    }

    /**
     * @depends testCreateImmediate
     */
    public function testCancelImmediate()
    {
        $immediate = $this->loop->immediate($this->createCallback(0));

        $immediate->cancel();

        $this->assertFalse($immediate->isPending());

        $this->loop->run();
    }

    /**
     * @depends testCreateImmediate
     * @expectedException \Icicle\Tests\Loop\LoopTestException
     */
    public function testRunThrowsAfterThrownExceptionFromImmediateCallback()
    {
        $exception = new LoopTestException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $immediate = $this->loop->immediate($callback);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Throwable $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from immediate callbacks.');
    }

    public function testUnreferenceImmediate()
    {
        $immediate = $this->loop->immediate($this->createCallback(0));

        $immediate->unreference();

        $this->assertTrue($this->loop->isEmpty());

        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME);

        $immediate->reference();

        $this->assertFalse($this->loop->isEmpty());
    }
    
    /**
     * @depends testNoBlockingOnEmptyLoop
     */
    public function testCreateTimer()
    {
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));

        $this->assertInstanceOf(Timer::class, $timer);

        $this->assertTrue($timer->isPending());
        
        $this->assertRunTimeBetween([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME, self::TIMEOUT + self::RUNTIME);
    }
    
    /**
     * @depends testCreateTimer
     */
    public function testOverdueTimer()
    {
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));
        
        usleep(self::TIMEOUT * 3 * self::MICROSEC_PER_SEC);
        
        $this->assertRunTimeLessThan([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME);
    }
    
    public function testUnreferenceTimer()
    {
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));

        $timer->unreference();

        $this->assertTrue($this->loop->isEmpty());

        $this->assertRunTimeLessThan([$this->loop, 'run'], self::RUNTIME);

        $timer->reference();
        
        $this->assertFalse($this->loop->isEmpty());

        $this->assertRunTimeGreaterThan([$this->loop, 'run'], self::TIMEOUT - self::RUNTIME * 2);
    }
    
    /**
     * @depends testCreateTimer
     */
    public function testCreatePeriodicTimer()
    {
        $timer = $this->loop->timer(self::TIMEOUT, true, $this->createCallback(2));
        
        $this->assertTrue($timer->isPending());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
    }
    
    /**
     * @depends testNoBlockingOnEmptyLoop
     * @depends testCreatePeriodicTimer
     */
    public function testStopTimer()
    {
        $timer = $this->loop->timer(self::TIMEOUT, true, $this->createCallback(1));
        
        $this->assertTrue($timer->isPending());
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(false);
        
        $timer->stop();
        
        $this->assertFalse($timer->isPending());

        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));

        $this->assertTrue($timer->isPending());

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->run();
    }
    
    /**
     * @depends testStopTimer
     * @depends testCreatePeriodicTimer
     */
    public function testTimerWithSelfStop()
    {
        $iterations = 3;
        
        $callback = $this->createCallback($iterations);
        $callback->method('__invoke')
                 ->will($this->returnCallback(function () use (&$timer, $iterations) {
                     static $count = 0;
                     ++$count;
                     if ($iterations === $count) {
                         $timer->stop();
                    }
                 }));
        
        $timer = $this->loop->timer(self::TIMEOUT, true, $callback);
        
        $this->loop->run();
    }

    /**
     * @depends testStopTimer
     */
    public function testStartTimer()
    {
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));

        $timer->stop();

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);

        $this->loop->tick(false);

        $timer->start();

        $this->assertTrue($timer->isPending());

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);

        $this->loop->run();
    }

    /**
     * @depends testStartTimer
     */
    public function testTimerImmediateRestart()
    {
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));

        $timer->stop();
        $timer->start();

        $this->assertTrue($timer->isPending());

        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);

        $this->loop->run();
    }

    /**
     * @medium
     * @depends testCreateTimer
     * @expectedException \Icicle\Tests\Loop\LoopTestException
     */
    public function testRunThrowsAfterThrownExceptionFromTimerCallback()
    {
        $exception = new LoopTestException();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $timer = $this->loop->timer(self::TIMEOUT, false, $callback);
        
        try {
            $this->loop->run(); // Exception should be thrown from loop.
        } catch (Throwable $e) {
            $this->assertSame($exception, $e);
            $this->assertFalse($this->loop->isRunning()); // Loop should report that it has stopped.
            throw $e;
        }
        
        $this->fail('Loop should not catch exceptions thrown from timer callbacks.');
    }

    /**
     * @requires extension pcntl
     */
    public function testSignalHandlingEnabled()
    {
        $this->assertTrue($this->loop->isSignalHandlingEnabled());
    }
    
    /**
     * @depends testSignalHandlingEnabled
     * @requires extension pcntl
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
        
        $signal = $this->loop->signal(SIGUSR1, $callback1);
        $this->assertInstanceOf(Signal::class, $signal);
        $this->assertTrue($signal->isEnabled());
        $signal = $this->loop->signal(SIGUSR2, $callback2);
        $this->assertInstanceOf(Signal::class, $signal);
        $this->assertTrue($signal->isEnabled());
        $signal = $this->loop->signal(SIGUSR1, $callback3);
        $this->assertInstanceOf(Signal::class, $signal);
        $this->assertTrue($signal->isEnabled());

        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.
        
        $this->loop->tick(true);
    }
    
    /**
     * @depends testSignal
     */
    public function testQuitSignalWithListener()
    {
        $pid = posix_getpid();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(SIGQUIT));
        
        $signal = $this->loop->signal(SIGQUIT, $callback);
        $this->assertTrue($signal->isEnabled());

        posix_kill($pid, SIGQUIT);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.
        
        $this->loop->tick(true);
    }
    
    /**
     * @medium
     * @depends testSignal
     */
    public function testQuitSignalWithNoListeners()
    {
        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->queue('posix_kill', [posix_getpid(), SIGQUIT]);
        
        $this->assertSame(true, $this->loop->run());
    }
    
    /**
     * @medium
     * @depends testSignal
     */
    public function testTerminateSignal()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(SIGTERM));
        
        $signal = $this->loop->signal(SIGTERM, $callback);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->queue('posix_kill', [posix_getpid(), SIGTERM]);

        $this->assertSame(true, $this->loop->run());
    }
    
    /**
     * @depends testSignal
     */
    public function testDisableSignal()
    {
        $pid = posix_getpid();

        $callback1 = $this->createCallback(2);
        $callback2 = $this->createCallback(1);

        $signal1 = $this->loop->signal(SIGUSR1, $callback1);
        $signal2 = $this->loop->signal(SIGUSR2, $callback2);

        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->tick(true);

        $signal2->disable();
        $this->assertFalse($signal2->isEnabled());

        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->tick(true);

        $signal1->disable();
        $this->assertFalse($signal1->isEnabled());

        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->tick(true);
    }

    /**
     * @depends testDisableSignal
     */
    public function testEnableSignal()
    {
        $pid = posix_getpid();

        $callback1 = $this->createCallback(1);
        $callback2 = $this->createCallback(1);

        $signal1 = $this->loop->signal(SIGUSR1, $callback1);
        $signal2 = $this->loop->signal(SIGUSR2, $callback2);

        $signal1->disable();
        $signal2->disable();

        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->tick(true);

        $signal1->enable();
        $signal2->enable();

        $this->assertTrue($signal1->isEnabled());
        $this->assertTrue($signal2->isEnabled());

        posix_kill($pid, SIGUSR1);
        posix_kill($pid, SIGUSR2);

        $this->loop->timer(1, false, function () {}); // Keep loop alive until signal arrives.

        $this->loop->tick(true);
    }

    /**
     * @depends testSignal
     * @expectedException \Icicle\Loop\Exception\InvalidSignalError
     */
    public function testInvalidSignal()
    {
        $this->loop->signal(-1, $this->createCallback(0));
    }

    /**
     * @depends testDisableSignal
     */
    public function testReferenceSignal()
    {
        $pid = posix_getpid();

        $signal = $this->loop->signal(SIGUSR1, function () use (&$signal) {
            $signal->unreference();
        });

        $this->loop->timer(1, false, function () use ($pid) {
            posix_kill($pid, SIGUSR1);
        })->unreference();

        $this->assertTrue($this->loop->isEmpty());

        $signal->reference();

        $this->assertFalse($this->loop->isEmpty());

        $this->assertRunTimeGreaterThan([$this->loop, 'run'], 1 - self::RUNTIME);
    }

    /**
     * @depends testCreatePoll
     * @depends testCreateAwait
     * @depends testCreateTimer
     * @depends testQueue
     */
    public function testIsEmpty()
    {
        list($readable, $writable) = $this->createSockets();
        
        $poll = $this->loop->poll($readable, $this->createCallback(1));
        $await = $this->loop->await($writable, $this->createCallback(1));
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));
        
        $this->loop->queue($this->createCallback(1));
        
        $poll->listen();
        $await->listen();
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(true);
        
        $this->assertTrue($this->loop->isEmpty());
    }
    
    /**
     * @depends testCreatePoll
     * @depends testCreateAwait
     * @depends testCreateImmediate
     * @depends testCreatePeriodicTimer
     * @depends testQueue
     */
    public function testClear()
    {
        list($readable, $writable) = $this->createSockets();
        
        $poll = $this->loop->poll($readable, $this->createCallback(0));
        $await = $this->loop->await($writable, $this->createCallback(0));
        $immediate = $this->loop->immediate($this->createCallback(0));
        $timer = $this->loop->timer(self::TIMEOUT, true, $this->createCallback(0));
        
        $this->loop->queue($this->createCallback(0));
        $poll->listen(self::TIMEOUT);
        $await->listen(self::TIMEOUT);
        
        $this->loop->clear();
        
        $this->assertTrue($this->loop->isEmpty());
        
        $this->assertFalse($poll->isPending());
        $this->assertFalse($await->isPending());
        $this->assertFalse($timer->isPending());
        $this->assertFalse($immediate->isPending());
        
        $this->assertTrue($poll->isFreed());
        $this->assertTrue($await->isFreed());
        
        $this->loop->run();
    }
    
    /**
     * @depends testCreatePoll
     * @depends testCreateAwait
     * @depends testCreatePeriodicTimer
     * @depends testQueue
     */
    public function testReInit()
    {
        list($readable, $writable) = $this->createSockets();
        
        $poll = $this->loop->poll($readable, $this->createCallback(1));
        $await = $this->loop->await($writable, $this->createCallback(1));
        $timer = $this->loop->timer(self::TIMEOUT, false, $this->createCallback(1));
        
        $this->loop->queue($this->createCallback(1));
        $poll->listen();
        $await->listen();
        
        $this->loop->reInit(); // Calling this function should not cancel any pending events.
        
        usleep(self::TIMEOUT * self::MICROSEC_PER_SEC);
        
        $this->loop->tick(true);
    }
}
