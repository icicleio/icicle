<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests;

use Icicle\Awaitable;
use Icicle\Coroutine;
use Icicle\Loop;

class FunctionsTestException extends \Exception {}

class FunctionsTest extends TestCase
{
    const TIMEOUT = 0.1;

    public function testExecute()
    {
        $arg1 = 1;
        $arg2 = 2;
        $arg3 = 3;

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($arg1), $this->identicalTo($arg2), $this->identicalTo($arg3));

        $this->assertFalse(\Icicle\execute($callback, $arg1, $arg2, $arg3));
    }

    /**
     * @depends testExecute
     */
    public function testExecuteThrowsIfCallableThrows()
    {
        $exception = new FunctionsTestException();

        try {
            \Icicle\execute(function () use ($exception) {
                throw $exception;
            });
            $this->fail('Exceptions thrown in execute() callable should be thrown from function.');
        } catch (FunctionsTestException $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testExecuteThrowsIfCallableThrows
     */
    public function testExecuteReportsRunningEventLoop()
    {
        \Icicle\execute(function () {
            $this->assertTrue(Loop\isRunning());
        });
    }

    /**
     * @depends testExecuteReportsRunningEventLoop
     */
    public function testExecuteReturnAwaitable()
    {
        $this->assertRunTimeGreaterThan('Icicle\execute', self::TIMEOUT, [function () {
            return Awaitable\resolve()->delay(self::TIMEOUT);
        }]);
    }

    /**
     * @depends testExecuteReturnAwaitable
     */
    public function testExecuteThrowsRejectedAwaitableReason()
    {
        $exception = new FunctionsTestException();

        try {
            \Icicle\execute(function () use ($exception) {
                return Awaitable\reject($exception);
            });
            $this->fail('Awaitable rejection reason should be thrown from execute().');
        } catch (FunctionsTestException $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testExecuteReportsRunningEventLoop
     */
    public function testExecuteCallableIsCoroutine()
    {
        $this->assertRunTimeGreaterThan('Icicle\execute', self::TIMEOUT, [function () {
            yield Coroutine\sleep(self::TIMEOUT);
        }]);
    }

    /**
     * @depends testExecuteReturnAwaitable
     */
    public function testExecuteThrowsCoroutineRejectionReason()
    {
        $exception = new FunctionsTestException();

        try {
            \Icicle\execute(function () use ($exception) {
                throw $exception;
                yield; // Unreachable, but makes function a coroutine.
            });
            $this->fail('Coroutine rejection reason should be thrown from execute().');
        } catch (FunctionsTestException $reason) {
            $this->assertSame($exception, $reason);
        }
    }
}
