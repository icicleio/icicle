<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Coroutine;

use Exception;
use Icicle\Awaitable;
use Icicle\Awaitable\{Awaitable as AwaitableInterface, Delayed};
use Icicle\Awaitable\Exception\{CancelledException, TimeoutException};
use Icicle\Coroutine\{Coroutine, function sleep};
use Icicle\Coroutine\Exception\{AwaitableReturnedError, GeneratorReturnedError, InvalidCallableError};
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Tests\TestCase;

class CoroutineTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testYieldScalar()
    {
        $value = 1;
        
        $generator = function () use (&$yielded, $value) {
            $yielded = yield $value;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertNull($coroutine->wait());
    }

    public function testFulfilledWithReturnValue()
    {
        $value = 1;

        $generator = function () use ($value) {
            return yield $value;
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $coroutine->done($callback);

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }

    public function testYieldFulfilledPromise()
    {
        $value = 1;
        
        $generator = function () use (&$yielded, $value) {
            return $yielded = yield Awaitable\resolve($value);
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }
    
    public function testYieldRejectedPromise()
    {
        $exception = new Exception();
        
        $generator = function () use (&$yielded, $exception) {
            return $yielded = yield Awaitable\reject($exception);
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertNull($yielded);
        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }
    
    /**
     * @depends testYieldFulfilledPromise
     */
    public function testYieldPendingPromise()
    {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            return $yielded = yield Awaitable\resolve($value)->delay(self::TIMEOUT);
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT);
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }
    
    /**
     * @depends testFulfilledWithReturnValue
     */
    public function testThen()
    {
        $value = 1;
        
        $generator = function () use ($value) {
            yield 0;
            return $value;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $child = $coroutine->then($callback, $this->createCallback(0));
        
        $this->assertInstanceOf(AwaitableInterface::class, $child);
        
        Loop\run();
        
        $this->assertTrue($child->isFulfilled());
    }
    
    /**
     * @depends testFulfilledWithReturnValue
     * @depends testYieldRejectedPromise
     */
    public function testCatchingRejectedPromiseException()
    {
        $value = 1;
        $exception = new Exception();
        
        $generator = function () use ($value, $exception) {
            try {
                yield Awaitable\reject($exception);
            } catch (Exception $exception) {
                return $value;
            }

            return 0;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }

    /**
     * @depends testYieldScalar
     * @depends testYieldRejectedPromise
     */
    public function testCatchingRejectedPromiseExceptionWithNoFurtherYields()
    {
        $exception = new Exception();

        $generator = function () use ($exception) {
            try {
                yield Awaitable\reject($exception);
            } catch (Exception $exception) {
                // No further yields in generator.
            }
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(null));

        $coroutine->done($callback);

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame(null, $coroutine->wait());
    }
    
    public function testGeneratorThrowingExceptionRejectsCoroutine()
    {
        $exception = new Exception();
        
        $generator = function () use ($exception) {
            throw $exception;
            yield;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testCatchingRejectedPromiseException
     */
    public function testGeneratorCatchingThrownExceptionWithoutFurtherYield()
    {
        $exception = new Exception();
        $value = 1;

        $generator = function () use ($exception, $value) {
            try {
                yield 0;
                throw $exception;
            } catch (Exception $exception) {
                // Exception caught, but no further yields.
            }

            return $value;
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $coroutine->done($callback);

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }

    /**
     * @depends testGeneratorThrowingExceptionRejectsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyRejectsCoroutine()
    {
        $exception = new Exception();

        $callback = $this->createCallback(1);

        $generator = function () use ($exception, $callback) {
            try {
                throw $exception;
                yield;
            } finally {
                $callback();
            }
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();

        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testYieldRejectedPromise
     * @depends testGeneratorThrowingExceptionWithFinallyRejectsCoroutine
     */
    public function testGeneratorYieldingRejectedPromiseWithFinallyRejectsCoroutine()
    {
        $exception = new Exception();

        $callback = $this->createCallback(1);

        $generator = function () use ($exception, $callback) {
            try {
                yield Awaitable\reject($exception);
            } finally {
                $callback();
            }
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();

        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testGeneratorThrowingExceptionRejectsCoroutine
     */
    public function testGeneratorThrowingExceptionAfterPendingPromiseWithFinallyRejectsCoroutine()
    {
        $exception = new Exception();
        $value = 1;

        $callback = $this->createCallback(1);

        $generator = function () use (&$yielded, $exception, $callback, $value) {
            try {
                $yielded = yield Awaitable\resolve($value)->delay(self::TIMEOUT);
                throw $exception;
            } finally {
                $callback();
            }
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT);

        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * Note that yielding in a finally block is not recommended.
     *
     * @depends testYieldPendingPromise
     * @depends testGeneratorThrowingExceptionWithFinallyRejectsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyYieldingPendingPromise()
    {
        $exception = new Exception();
        $value = 1;

        $generator = function () use (&$yielded, $exception, $value) {
            try {
                throw $exception;
            } finally {
                $yielded = yield Awaitable\resolve($value)->delay(self::TIMEOUT);
            }
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT);

        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }

    /**
     * @depends testYieldPendingPromise
     * @depends testGeneratorThrowingExceptionWithFinallyRejectsCoroutine
     */
    public function testGeneratorThrowingExceptionWithFinallyBlockThrowing()
    {
        $exception = new Exception();

        $generator = function () use (&$yielded, $exception) {
            try {
                throw new Exception();
            } finally {
                throw $exception;
            }

            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();

        $this->assertTrue($coroutine->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }
    
    /**
     * @depends testFulfilledWithReturnValue
     */
    public function testYieldGenerator()
    {
        $value = 1;
        
        $generator = function () use ($value) {
            $generator = function () use ($value) {
                yield 0;
                return $value;
            };
            
            return yield $generator();
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }

    /**
     * @depends testYieldGenerator
     */
    public function testYieldFromGenerator()
    {
        $value = 1;

        $generator = function () use ($value) {
            $generator = function () use ($value) {
                yield 0;
                return $value;
            };

            return yield from $generator();
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $coroutine->done($callback);

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }

    /**
     * @depends testYieldFulfilledPromise
     */
    public function testYieldConsecutiveFulfilled()
    {
        $count = 1000;
        $awaitable = Awaitable\resolve();

        $generator = function () use ($count, $awaitable) {
            for ($i = 0; $i < $count; ++$i) {
                yield $awaitable;
            }
        };

        $coroutine = new Coroutine($generator());
        $coroutine->done($this->createCallback(1));

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
    }

    /**
     * @depends testYieldRejectedPromise
     */
    public function testYieldConsecutiveRejected()
    {
        $count = 1000;
        $awaitable = Awaitable\reject(new Exception());

        $generator = function () use ($count, $awaitable) {
            for ($i = 0; $i < $count; ++$i) {
                try {
                    yield $awaitable;
                } catch (Exception $exception) {
                    // Ignore and continue.
                }
            }
        };

        $coroutine = new Coroutine($generator());
        $coroutine->done($this->createCallback(1));

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
    }
    
    public function testCancellation()
    {
        $generator = function () {
            yield 1;
            yield 2;
            yield 3;
        };
        
        $coroutine = new Coroutine($generator());
        
        $this->assertTrue($coroutine->isPending());
        
        $coroutine->cancel();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(CancelledException::class));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isRejected());
    }

    /**
     * @depends testCancellation
     */
    public function testCancellationThrowsIntoGenerator()
    {
        $exception = new Exception();

        $generator = function () use ($exception) {
            try {
                yield new Delayed();
            } catch (Exception $reason) {
                $this->assertSame($exception, $reason);
                throw $reason;
            }
        };

        $coroutine = new Coroutine($generator());

        $this->assertTrue($coroutine->isPending());

        $coroutine->cancel($exception);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();

        $this->assertTrue($coroutine->isRejected());
    }

    /**
     * @depends testCancellationThrowsIntoGenerator
     */
    public function testCancellationAllowsGeneratorToContinueIfExceptionCaught()
    {
        $exception = new Exception();
        $run = false;

        $generator = function () use (&$run, $exception) {
            try {
                yield new Delayed();
            } catch (Exception $reason) {
                // Catch and ignore cancellation reason.
            }

            yield Awaitable\resolve()->delay(self::TIMEOUT);

            $run = true;
        };

        $coroutine = new Coroutine($generator());

        $this->assertTrue($coroutine->isPending());

        $coroutine->cancel();

        $coroutine->done($this->createCallback(0), $this->createCallback(1));

        Loop\run();

        $this->assertTrue($run);
    }

    /**
     * @depends testCancellation
     */
    public function testCancellationWithSpecificException()
    {
        $exception = new Exception();
        
        $generator = function () {
            yield 1;
            yield 2;
            yield 3;
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
    }

    /**
     * @depends testCancellationWithSpecificException
     */
    public function testCancellationWithPendingPromise()
    {
        $exception = new Exception();
        
        $generator = function () use (&$promise) {
            yield ($promise = Awaitable\resolve(1)->delay(0.1));
        };
        
        $coroutine = new Coroutine($generator());
        
        $this->assertTrue($coroutine->isPending());
        $this->assertInstanceOf(AwaitableInterface::class, $promise);
        
        $coroutine->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($promise->isRejected());

        try {
            $coroutine->wait();
        } catch (Exception $reason) {
            $this->assertSame($exception, $reason);
        }
    }
    
    /**
     * @depends testCancellationWithPendingPromise
     */
    public function testCancellationWithFinallyBlock()
    {
        $exception = new Exception();
        $executed = false;

        $generator = function () use (&$executed) {
            try {
                yield Awaitable\resolve(1)->delay(0.1);
            } finally {
                $executed = true;
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($executed);
    }

    /**
     * @depends testCancellationWithPendingPromise
     */
    public function testCancellationWithThrowFromFinallyBlock()
    {
        $exception = new Exception();

        $generator = function () use ($exception) {
            try {
                yield Awaitable\resolve(1)->delay(0.1);
            } finally {
                throw $exception; // Exception is thrown after cancellation and ignored.
            }
        };

        $coroutine = new Coroutine($generator());

        $this->assertTrue($coroutine->isPending());

        $coroutine->cancel();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(CancelledException::class));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();

        $this->assertTrue($coroutine->isCancelled());
    }

    /**
     * @depends testCancellation
     */
    public function testCancellationOnFulfilledCoroutine()
    {
        $value = 1;

        $generator = function () use ($value) {
            return yield $value;
        };

        $coroutine = new Coroutine($generator());

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());

        $coroutine->cancel();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $coroutine->done($callback);

        Loop\run();
    }

    /**
     * @depends testCancellation
     */
    public function testCancellationOnRejectedCoroutine()
    {
        $exception = new Exception();

        $generator = function () use ($exception) {
            throw $exception;

            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator());

        $this->assertTrue($coroutine->isRejected());

        $coroutine->cancel();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $coroutine->done($this->createCallback(0), $callback);

        Loop\run();
    }

    /**
     * @depends testCancellation
     */
    public function testTimeout()
    {
        $generator = function () use (&$promise) {
            yield ($promise = new Delayed()); // Yield promise that will never resolve.
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(TimeoutException::class));
        
        $timeout = $coroutine->timeout(self::TIMEOUT, function () use ($coroutine) {
            $exception = new TimeoutException();
            $coroutine->cancel($exception);
            throw $exception;
        });
        
        $this->assertInstanceOf(AwaitableInterface::class, $timeout);
        
        $timeout->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isRejected());
        $this->assertTrue($timeout->isRejected());
        $this->assertTrue($promise->isRejected());
    }
    
    public function testDelay()
    {
        $value = 1;
        
        $generator = function () use ($value) {
            yield 0;
            return $value;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $delayed = $coroutine->delay(self::TIMEOUT);
        
        $this->assertInstanceOf(AwaitableInterface::class, $delayed);
        
        $delayed->done($callback);
        
        Loop\run();
        
        $this->assertTrue($delayed->isFulfilled());
        $this->assertSame($value, $delayed->wait());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testCooperation()
    {
        $generator = function ($id, $count = 0) {
            for ($i = 0; $count > $i; ++$i) {
                echo (yield "[{$id}]");
            }
        };
        
        $coroutine1 = new Coroutine($generator(1, 8));
        $coroutine2 = new Coroutine($generator(2, 5));
        $coroutine3 = new Coroutine($generator(3, 2));
        
        $this->expectOutputString('[1][2][3][1][2][3][1][2][1][2][1][2][1][1][1]');
        
        Loop\run();
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testFastInvalidGenerator()
    {
        $generator = function () {
            if (false) {
                yield 1;
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame(null, $coroutine->wait());
    }

    /**
     * @depends testFastInvalidGenerator
     */
    public function testFastReturningGenerator()
    {
        $value = 1;

        $generator = function () use ($value) {
            if (true) {
                return $value;
            }

            yield;

            return -$value;
        };

        $coroutine = new Coroutine($generator());

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->wait());
    }
    
    /**
     * @depends testFulfilledWithReturnValue
     */
    public function testWrap()
    {
        $wrap = \Icicle\Coroutine\wrap(function ($left, $right) {
            return yield $left - $right;
        });
        
        $this->assertTrue(is_callable($wrap));
        
        $coroutine1 = $wrap(1, 2);
        $coroutine2 = $wrap(5, -5);
        
        $this->assertInstanceOf(Coroutine::class, $coroutine1);
        $this->assertInstanceOf(Coroutine::class, $coroutine2);
        $this->assertNotSame($coroutine1, $coroutine2);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(-1));
        
        $coroutine1->done($callback);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(10));
        
        $coroutine2->done($callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine1->isFulfilled());
        $this->assertSame(-1, $coroutine1->wait());
        
        $this->assertTrue($coroutine2->isFulfilled());
        $this->assertSame(10, $coroutine2->wait());
    }
    
    /**
     * @depends testWrap
     */
    public function testWrapWithNonGeneratorCallable()
    {
        $callback = function () {};
        
        $wrap = \Icicle\Coroutine\wrap($callback);

        $coroutine = $wrap();

        try {
            $coroutine->wait();
            $this->fail(sprintf('Expected exception of type %s', InvalidCallableError::class));
        } catch (InvalidCallableError $exception) {
            $this->assertSame($callback, $exception->getCallable());
        }
    }
    
    /**
     * @depends testWrap
     */
    public function testWrapWithCallableThrowingException()
    {
        $callback = function () {
            throw new Exception();
        };
        
        $wrap = \Icicle\Coroutine\wrap($callback);

        $coroutine = $wrap();

        try {
            $coroutine->wait();
            $this->fail(sprintf('Expected exception of type %s', InvalidCallableError::class));
        } catch (InvalidCallableError $exception) {
            $this->assertSame($callback, $exception->getCallable());
        }
    }
    
    /**
     * @depends testFulfilledWithReturnValue
     */
    public function testCreate()
    {
        $coroutine = \Icicle\Coroutine\create(function ($left, $right) {
            return yield $left - $right;
        }, 1, 2);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(-1));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame(-1, $coroutine->wait());
    }
    
    /**
     * @depends testCreate
     */
    public function testCreateWithNonGeneratorCallable()
    {
        $callback = function () {
        };

        $coroutine = \Icicle\Coroutine\create($callback);

        try {
            $coroutine->wait();
            $this->fail(sprintf('Expected exception of type %s', InvalidCallableError::class));
        } catch (InvalidCallableError $exception) {
            $this->assertSame($callback, $exception->getCallable());
        }
    }
    
    /**
     * @depends testCreate
     */
    public function testCreateWithCallableThrowningException()
    {
        $callback = function () {
            throw new Exception();
        };

        $coroutine = \Icicle\Coroutine\create($callback);

        try {
            $coroutine->wait();
            $this->fail(sprintf('Expected exception of type %s', InvalidCallableError::class));
        } catch (InvalidCallableError $exception) {
            $this->assertSame($callback, $exception->getCallable());
        }
    }
    
    /**
     * @depends testYieldGenerator
     */
    public function testSleep()
    {
        $coroutine = new Coroutine(sleep(self::TIMEOUT));
        
        Loop\run();
        
        $this->assertGreaterThanOrEqual(self::TIMEOUT, $coroutine->wait());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testResolvePromiseWithCoroutine()
    {
        $value = 'test';
        $generator = function () use ($value) {
            return yield 'test';
        };

        $promise = new Delayed();
        $promise->resolve(new Coroutine($generator()));

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $promise->done($callback);
        
        Loop\run();
    }

    /**
     * @depends testFulfilledWithReturnValue
     */
    public function testReturningAwaitableRejectsCoroutine()
    {
        $awaitable = Awaitable\resolve();
        $generator = function () use ($awaitable) {
            return $awaitable;
            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator());

        try {
            $coroutine->wait();
            $this->fail(sprintf('Expected exception of type %s', AwaitableReturnedError::class));
        } catch (AwaitableReturnedError $exception) {
            $this->assertSame($awaitable, $exception->getAwaitable());
        }
    }

    /**
     * @depends testFulfilledWithReturnValue
     */
    public function testReturningGeneratorRejectsCoroutine()
    {
        $generator1 = (function () { yield; })();
        $generator2 = function () use ($generator1) {
            return $generator1;
            yield; // Unreachable, but makes function a generator.
        };

        $coroutine = new Coroutine($generator2());

        try {
            $coroutine->wait();
            $this->fail(sprintf('Expected exception of type %s', GeneratorReturnedError::class));
        } catch (GeneratorReturnedError $exception) {
            $this->assertSame($generator1, $exception->getGenerator());
        }
    }
}
