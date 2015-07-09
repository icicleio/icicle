<?php
namespace Icicle\Tests\Coroutine;

use Exception;
use Icicle\Coroutine\Coroutine;
use Icicle\Coroutine\Exception\InvalidCallableError;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\Exception\CancelledException;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Promise\PromiseInterface;
use Icicle\Tests\TestCase;

class CoroutineTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    public function tearDown()
    {
        Loop\clear();
    }
    
    public function testYieldScalar()
    {
        $value = 1;
        
        $generator = function () use (&$yielded, $value) {
            $yielded = (yield $value);
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->getResult());
    }
    
    public function testYieldFulfilledPromise()
    {
        $value = 1;
        
        $generator = function () use (&$yielded, $value) {
            $yielded = (yield Promise\resolve($value));
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->getResult());
    }
    
    public function testYieldRejectedPromise()
    {
        $exception = new Exception();
        
        $generator = function () use (&$yielded, $exception) {
            $yielded = (yield Promise\reject($exception));
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertNull($yielded);
        $this->assertTrue($coroutine->isRejected());
        $this->assertSame($exception, $coroutine->getResult());
    }
    
    /**
     * @depends testYieldFulfilledPromise
     */
    public function testYieldPendingPromise()
    {
        $value = 1;

        $generator = function () use (&$yielded, $value) {
            $yielded = (yield Promise\resolve($value)->delay(self::TIMEOUT));
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT);
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->getResult());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testThen()
    {
        $value = 1;
        
        $generator = function () use ($value) {
            yield $value;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $child = $coroutine->then($callback, $this->createCallback(0));
        
        $this->assertInstanceOf(PromiseInterface::class, $child);
        
        Loop\run();
        
        $this->assertTrue($child->isFulfilled());
    }
    
    /**
     * @depends testYieldScalar
     * @depends testYieldRejectedPromise
     */
    public function testCatchingRejectedPromiseException()
    {
        $value = 1;
        $exception = new Exception();
        
        $generator = function () use ($value, $exception) {
            try {
                yield Promise\reject($exception);
            } catch (Exception $exception) {
                yield $value;
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->getResult());
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
        $this->assertSame($exception, $coroutine->getResult());
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
                yield $value;
                throw $exception;
            } catch (Exception $exception) {
                // Exception caught, but no further yields.
            }
        };

        $coroutine = new Coroutine($generator());

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $coroutine->done($callback);

        Loop\run();

        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->getResult());
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
        $this->assertSame($exception, $coroutine->getResult());
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
                yield Promise\reject($exception);
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
        $this->assertSame($exception, $coroutine->getResult());
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
                $yielded = (yield Promise\resolve($value)->delay(self::TIMEOUT));
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
        $this->assertSame($exception, $coroutine->getResult());
    }

    /**
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
                $yielded = (yield Promise\resolve($value)->delay(self::TIMEOUT));
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
        $this->assertSame($exception, $coroutine->getResult());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testYieldGenerator()
    {
        $value = 1;
        
        $generator = function () use (&$yielded, $value) {
            $generator = function () use ($value) {
                yield $value;
            };
            
            $yielded = (yield $generator());
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertSame($value, $yielded);
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame($value, $coroutine->getResult());
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
    public function testCancellationWithTryCatchBlock()
    {
        $exception = new Exception();
        
        $generator = function () {
            try {
                yield 1;
            } catch (Exception $exception) {
                // Cleanup code, generator no longer valid.
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isRejected());
        $this->assertSame($exception, $coroutine->getResult());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellationWithThrownException()
    {
        $exception = new Exception();
        
        $generator = function () use ($exception) {
            try {
                yield 1;
            } catch (Exception $e) {
                throw $exception;
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->cancel(); // Uses default cancellation exception.
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isRejected());
        $this->assertSame($exception, $coroutine->getResult());
    }
    
    /**
     * @depends testCancellationWithSpecificException
     */
    public function testCancellationWithPendingPromise()
    {
        $exception = new Exception();
        
        $generator = function () use (&$promise) {
            yield ($promise = Promise\resolve(1)->delay(0.1));
        };
        
        $coroutine = new Coroutine($generator());
        
        Loop\tick(); // Get to first yield statement.
        
        $this->assertTrue($coroutine->isPending());
        $this->assertInstanceOf(Promise\Promise::class, $promise);
        
        $coroutine->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertTrue($promise->isRejected());
        $this->assertSame($exception, $promise->getResult());
    }
    
    /**
     * @depends testCancellationWithPendingPromise
     */
    public function testCancellationWithTryCatchYieldingPendingPromise()
    {
        $exception = new Exception();
        
        $generator = function () use (&$promise) {
            try {
                yield Promise\resolve(1)->delay(0.1);
            } catch (Exception $exception) {
                yield ($promise = Promise\resolve(2)->delay(0.1));
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        Loop\tick(); // Get to first yield statement.
        
        $coroutine->cancel($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));
        
        $coroutine->done($this->createCallback(0), $callback);
        
        Loop\run();
        
        $this->assertInstanceOf(Promise\Promise::class, $promise);
        $this->assertTrue($promise->isRejected());
        $this->assertSame($exception, $promise->getResult());
    }
    
    /**
     * @depends testCancellation
     */
    public function testTimeout()
    {
        $generator = function () use (&$promise) {
            yield ($promise = new Promise\Promise(function () {
            })); // Yield promise that will never resolve.
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf(TimeoutException::class));
        
        $timeout = $coroutine->timeout(self::TIMEOUT);
        
        $this->assertInstanceOf(PromiseInterface::class, $timeout);
        
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
            yield $value;
        };
        
        $coroutine = new Coroutine($generator());
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));
        
        $delayed = $coroutine->delay(self::TIMEOUT);
        
        $this->assertInstanceOf(PromiseInterface::class, $delayed);
        
        $delayed->done($callback);
        
        Loop\run();
        
        $this->assertTrue($delayed->isFulfilled());
        $this->assertSame($value, $delayed->getResult());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testCooperation()
    {
        $generator = function ($id, $count = 0) {
            for ($i = 0; $count > $i; ++$i) {
                echo "[{$id}]";
                yield;
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
    public function testInvalidGenerator()
    {
        $generator = function () {
            if (false) {
                yield 1;
            }
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->done($this->createCallback(1));
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame(null, $coroutine->getResult());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testWrap()
    {
        $wrap = \Icicle\Coroutine\wrap(function ($left, $right) {
            yield $left - $right;
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
        $this->assertSame(-1, $coroutine1->getResult());
        
        $this->assertTrue($coroutine2->isFulfilled());
        $this->assertSame(10, $coroutine2->getResult());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testWrapWithNonGeneratorCallable()
    {
        $callback = function () {
        };
        
        $wrap = \Icicle\Coroutine\wrap($callback);
        
        try {
            $coroutine = $wrap();
            $this->fail(sprintf('Expected exception of type %s', InvalidCallableError::class));
        } catch (InvalidCallableError $exception) {
            $this->assertSame($callback, $exception->getCallable());
        }
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testWrapWithCallableThrowingException()
    {
        $callback = function () {
            throw new Exception();
        };
        
        $wrap = \Icicle\Coroutine\wrap($callback);
        
        try {
            $coroutine = $wrap();
            $this->fail(sprintf('Expected exception of type %s', InvalidCallableError::class));
        } catch (InvalidCallableError $exception) {
            $this->assertSame($callback, $exception->getCallable());
        }
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testCreate()
    {
        $coroutine = \Icicle\Coroutine\create(function ($left, $right) {
            yield $left - $right;
        }, 1, 2);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(-1));
        
        $coroutine->done($callback);
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame(-1, $coroutine->getResult());
    }
    
    /**
     * @depends testCreate
     */
    public function testCreateWithNonGeneratorCallable()
    {
        $callback = function () {
        };
        
        try {
            $coroutine = \Icicle\Coroutine\create($callback);
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
        
        try {
            $coroutine = \Icicle\Coroutine\create($callback);
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
        $coroutine = new Coroutine(\Icicle\Coroutine\sleep(self::TIMEOUT));
        
        Loop\run();
        
        $this->assertGreaterThanOrEqual(self::TIMEOUT, $coroutine->getResult());
    }

    /**
     * @depends testYieldGenerator
     */
    public function testPause()
    {
        $generator = function () {
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
        };
        
        $coroutine = new Coroutine($generator());
        
        $coroutine->pause();
        
        $this->assertRunTimeLessThan('Icicle\Loop\run', self::TIMEOUT);
    }
    
    /**
     * @depends testPause
     */
    public function testResume()
    {
        $generator = function () use (&$coroutine) {
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
            
            $coroutine->pause();
            
            yield $coroutine->isPaused();
            
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
        };
        
        $coroutine = new Coroutine($generator());
        
        $this->assertRunTimeBetween('Icicle\Loop\run', self::TIMEOUT, self::TIMEOUT * 2);
        
        $coroutine->resume();
        
        Loop\run();
        
        $coroutine->resume();
        
        Loop\run();
        
        $this->assertFalse($coroutine->isPending());
        $this->assertGreaterThanOrEqual(self::TIMEOUT, $coroutine->getResult());
    }
    
    /**
     * @depends testResume
     */
    public function testResumeImmediatelyAfterPause()
    {
        $generator = function () use (&$coroutine) {
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
            
            $coroutine->pause();
            $coroutine->resume();
            
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
        };
        
        $coroutine = new Coroutine($generator());
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT * 2);
    }
    
    /**
     * @depends testResume
     */
    public function testResumeOnPendingPromise()
    {
        $generator = function () use (&$coroutine) {
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
            
            $coroutine->pause();
            
            Loop\queue([$coroutine, 'resume']);
            
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
        };
        
        $coroutine = new Coroutine($generator());
        
        Loop\run();
        
        $this->assertFalse($coroutine->isPending());
        $this->assertGreaterThanOrEqual(self::TIMEOUT, $coroutine->getResult());
    }
    
    /**
     * @depends testResume
     */
    public function testResumeOnFulfilledPromise()
    {
        $generator = function () use (&$coroutine) {
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
            
            $coroutine->pause();
            
            yield Promise\resolve(1);
        };
        
        $coroutine = new Coroutine($generator());
        
        Loop\run();
        
        $coroutine->resume();
        
        $this->assertTrue($coroutine->isPending());
        
        Loop\run();
        
        $this->assertTrue($coroutine->isFulfilled());
        $this->assertSame(1, $coroutine->getResult());
    }
    
    /**
     * @depends testResume
     */
    public function testResumeOnRejectedPromise()
    {
        $exception = new Exception();
        
        $generator = function () use (&$coroutine, &$exception) {
            yield \Icicle\Coroutine\sleep(self::TIMEOUT);
            
            $coroutine->pause();
            
            yield $promise = Promise\reject($exception);
        };
        
        $coroutine = new Coroutine($generator());
        
        Loop\run();
        
        $coroutine->resume();
        
        $this->assertTrue($coroutine->isPending());
        
        Loop\run();
        
        $this->assertTrue($coroutine->isRejected());
        $this->assertSame($exception, $coroutine->getResult());
    }

    /**
     * @depends testResume
     */
    public function testMultiplePauseAndResume()
    {
        $generator = function () use (&$coroutine) {
            $coroutine->pause();

            yield \Icicle\Coroutine\sleep(self::TIMEOUT);

            $coroutine->pause();

            yield \Icicle\Coroutine\sleep(self::TIMEOUT);

            $coroutine->pause();

            yield \Icicle\Coroutine\sleep(self::TIMEOUT);

            $coroutine->pause();

            yield \Icicle\Coroutine\sleep(self::TIMEOUT);

            throw new Exception('Coroutine should not reach this point.');
        };

        $coroutine = new Coroutine($generator());

        Loop\run();

        $coroutine->resume();

        Loop\run();

        $coroutine->resume();

        Loop\run();

        Loop\run();

        $coroutine->resume();

        $this->assertTrue($coroutine->isPending());
    }
    
    /**
     * @depends testYieldScalar
     */
    public function testResolvePromiseWithCoroutine()
    {
        $value = 'test';
        $generator = function () use ($value) {
            yield 'test';
        };
        
        $promise = new Promise\Promise(function ($resolve) use ($generator) {
            $resolve(new Coroutine($generator()));
        });
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $promise->done($callback);
        
        Loop\run();
    }
}
