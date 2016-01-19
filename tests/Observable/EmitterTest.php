<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Observable\Emitter;
use Icicle\Observable\Exception\DisposedException;
use Icicle\Observable\Exception\InvalidEmitterError;
use Icicle\Observable\ObservableIterator;
use Icicle\Tests\TestCase;

class EmitterTestException extends \Exception {}

class EmitterTest extends TestCase
{
    const TIMEOUT = 0.1;

    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }

    public function testGetIterator()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit();
        });

        $iterator = $emitter->getIterator();

        $this->assertInstanceOf(ObservableIterator::class, $iterator);
    }

    public function testNonGeneratorCallable()
    {
        $callable = function () {};

        $emitter = new Emitter($callable);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        try {
            $awaitable->wait();
        } catch (InvalidEmitterError $exception) {
            $this->assertSame($callable, $exception->getCallable());
        }
    }

    public function testCallableThrowing()
    {
        $exception = new EmitterTestException();
        $callable = function () use ($exception) {
            throw $exception;
        };

        $emitter = new Emitter($callable);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        try {
            $awaitable->wait();
        } catch (InvalidEmitterError $reason) {
            $this->assertSame($callable, $reason->getCallable());
            $this->assertSame($exception, $reason->getPrevious());
        }
    }

    public function testEmit()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $awaitable = new Coroutine($emitter->each($callback));

        Loop\run();

        $this->assertTrue($emitter->isComplete());
        $this->assertFalse($emitter->isFailed());

        $this->assertTrue($awaitable->isFulfilled());
    }

    /**
     * @depends testEmit
     */
    public function testEmitAwaitable()
    {
        $delayed = new Delayed();

        $emitter = new Emitter(function (callable $emit) use ($delayed) {
            yield $emit($delayed);
        });

        $value = 1;

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $awaitable = new Coroutine($emitter->each($callback));

        $delayed->resolve($value);

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($emitter->isComplete());
        $this->assertFalse($emitter->isFailed());
    }

    /**
     * @depends testEmit
     */
    public function testEmitBackPressure()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use (&$time, $value) {
            $time = microtime(true);
            yield $emit();
            $time = microtime(true) - $time;
            yield $value;
        });

        $awaitable = new Coroutine($emitter->each(function () {
            yield Awaitable\resolve()->delay(self::TIMEOUT);
        }));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($emitter->isComplete());

        $this->assertGreaterThan(self::TIMEOUT - self::RUNTIME_PRECISION, $time);
    }

    /**
     * @depends testEmit
     */
    public function testSimultaneousEmitWaitsForFirstEmit()
    {
        $emitter = new Emitter(function (callable $emit) {
            $awaitable = Awaitable\resolve()->delay(self::TIMEOUT);
            $coroutine1 = new Coroutine($emit($awaitable));
            $coroutine2 = new Coroutine($emit($awaitable->delay(self::TIMEOUT)));

            yield Awaitable\all([$coroutine1, $coroutine2]);
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(2)));

        $awaitable->done();

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT * 2 - self::RUNTIME_PRECISION);
    }

    /**
     * @depends testSimultaneousEmitWaitsForFirstEmit
     * @expectedException \Icicle\Observable\Exception\CompletedError
     */
    public function testSimultaneousEmitAfterCompleted()
    {
        $emitter = new Emitter(function (callable $emit) use (&$coroutine2) {
            $awaitable = Awaitable\resolve()->delay(self::TIMEOUT);
            $coroutine1 = new Coroutine($emit($awaitable));
            $coroutine2 = new Coroutine($emit($awaitable->delay(self::TIMEOUT)));
            $coroutine2->done();

            yield $coroutine1;
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(1)));

        Loop\run();
    }

    /**
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testObservableFailedAfterEmittingRejectedAwaitable()
    {
        $emitter = new Emitter(function (callable $emit) use (&$coroutine2) {
            $coroutine = new Coroutine($emit(Awaitable\reject(new EmitterTestException())));
            yield new Delayed();
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        $awaitable->done();

        Loop\run();
    }

    /**
     * @depends testObservableFailedAfterEmittingRejectedAwaitable
     * @depends testSimultaneousEmitWaitsForFirstEmit
     * @expectedException \Icicle\Observable\Exception\CompletedError
     */
    public function testEmitAfterEmittingRejectedAwaitable()
    {
        $emitter = new Emitter(function (callable $emit) use (&$coroutine2) {
            $coroutine1 = new Coroutine($emit(Awaitable\reject(new EmitterTestException())));
            $coroutine2 = new Coroutine($emit(1));
            $coroutine2->done();

            yield $coroutine1;
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\run();
    }

    /**
     * @depends testEmit
     */
    public function testEmitObservable()
    {
        $observable = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield 4;
        });

        $emitter = new Emitter(function (callable $emit) use ($observable) {
            yield $emit($observable);
        });

        $i = 0;
        $awaitable = new Coroutine($emitter->each(function ($emitted) use (&$i) {
            $this->assertSame(++$i, $emitted);
        }));

        $this->assertSame(4, $awaitable->wait());
    }

    /**
     * @depends testEmitObservable
     */
    public function testEmitObservableEmittingObservable()
    {
        $observable1 = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield 4;
        });

        $observable2 = new Emitter(function (callable $emit) {
            yield $emit(8);
            yield $emit(9);
            yield $emit(10);
            yield $emit(11);
            yield 12;
        });

        $observable3 = new Emitter(function (callable $emit) use ($observable1, $observable2) {
            $result = (yield $emit($observable1));
            yield $emit($result);
            yield $emit(5);
            yield $emit(6);
            yield $emit(7);
            yield $emit($observable2);
        });

        $emitter = new Emitter(function (callable $emit) use ($observable3) {
            yield $emit($observable3);
        });

        $i = 0;
        $awaitable = new Coroutine($emitter->each(function ($emitted) use (&$i) {
            $this->assertSame(++$i, $emitted);
        }));

        $this->assertSame(12, $awaitable->wait());
    }

    /**
     * @depends testEmitObservable
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testEmitFailingObservable()
    {
        $observable = new Emitter(function (callable $emit) {
            yield $emit();
            throw new EmitterTestException();
        });

        $emitter = new Emitter(function (callable $emit) use ($observable) {
            yield $emit($observable);
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(1)));

        $awaitable->wait();
    }

    /**
     * @depends testEmitObservable
     */
    public function testEmitCompletedObservable()
    {
        $observable = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield 2;
        });

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));
        $this->assertSame(2, $awaitable->wait());

        $this->assertTrue($observable->isComplete());

        $emitter = new Emitter(function (callable $emit) use ($observable) {
            yield $emit($observable);
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));
        $this->assertSame(2, $awaitable->wait());
    }

    /**
     * @depends testEmitCompletedObservable
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testEmitFailedObservable()
    {
        $observable = new Emitter(function (callable $emit) {
            yield $emit();
            throw new EmitterTestException();
        });

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));

        Loop\run();

        $this->assertTrue($observable->isComplete());
        $this->assertTrue($observable->isFailed());

        $emitter = new Emitter(function (callable $emit) use ($observable) {
            yield $emit($observable);
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));
        $awaitable->wait();
    }

    /**
     * @depends testEmitObservable
     * @expectedException \Icicle\Observable\Exception\CircularEmitError
     */
    public function testEmitSelf()
    {
        $emitter = new Emitter(function (callable $emit) use (&$emitter) {
            yield $emit($emitter);
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));
        $awaitable->wait();
    }

    /**
     * @depends testEmit
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testEachCallbackThrowingRejectsCoroutine()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit();
        });

        $awaitable = new Coroutine($emitter->each(function () {
            throw new EmitterTestException();
        }));

        $awaitable->wait();
    }

    /**
     * @depends testEachCallbackThrowingRejectsCoroutine
     */
    public function testMap()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
        });

        $observable = $emitter->map(function ($value) {
            return $value + 1;
        });

        $awaitable = new Coroutine($observable->each(function ($emitted) use ($value) {
            $this->assertSame($value + 1, $emitted);
        }));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testMap
     */
    public function testMapWithOnComplete()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
        });

        $callback = function ($value) {
            return $value + 1;
        };

        $observable = $emitter->map($callback, $callback);

        $awaitable = new Coroutine($observable->each(function ($emitted) use ($value) {
            $this->assertSame($value + 1, $emitted);
        }));

        $this->assertSame($value + 1, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testEachCallbackThrowingRejectsCoroutine
     */
    public function testMapCoroutine()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
            yield $value;
        });

        $i = 0;

        $observable = $emitter->map(function ($value) use (&$i) {
            yield $value++;
        });

        $awaitable = new Coroutine($observable->each(function ($emitted) use (&$i, $value) {
            $this->assertSame($value + $i++, $emitted);
        }));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testMap
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testMapCallbackThrows()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit();
        });

        $observable = $emitter->map(function () {
            throw new EmitterTestException();
        });

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testMap
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testMapErroredEmitter()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
            throw new EmitterTestException();
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $observable = $emitter->map($callback);

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     */
    public function testFilter()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit(0);
            yield $emit(1);
            yield $emit(2);
            yield $value;
        });

        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return 1 === $value;
        };

        $observable = $emitter->filter($callback);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(1));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($awaitable->isFulfilled());
        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testFilter
     */
    public function testFilterCoroutine()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit(0);
            yield $emit(1);
            yield $emit(2);
            yield $value;
        });

        $callback = function ($value) {
            yield 1 === $value;
        };

        $observable = $emitter->filter($callback);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(1));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testFilter
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testFilterCallbackThrows()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit();
        });

        $observable = $emitter->filter(function () {
            throw new EmitterTestException();
        });

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testFilter
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testFilterErroredEmitter()
    {
        $value = 1;
        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
            throw new EmitterTestException();
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $observable = $emitter->filter($callback);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testMap
     */
    public function testSplat()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit([1, 2, 3]);
            yield $emit(new \ArrayIterator([1, 2, 3]));
            yield $value;
        });

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo(1), $this->identicalTo(2), $this->identicalTo(3));

        $observable = $emitter->splat($callback);

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo(null));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testSplat
     */
    public function testSplatWithOnComplete()
    {
        $value = new \ArrayObject([1, 2, 3]);

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit([1, 2, 3]);
            yield $emit(new \ArrayIterator([1, 2, 3]));
            yield $value;
        });

        $callback = $this->createCallback(3);
        $callback->method('__invoke')
            ->with($this->identicalTo(1), $this->identicalTo(2), $this->identicalTo(3));

        $observable = $emitter->splat($callback, $callback);

        $awaitable = new Coroutine($observable->each($this->createCallback(2)));

        $this->assertSame(null, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @return array
     */
    public function getInvalidSplatValues()
    {
        return [
            ['test'],
            [3.14],
            [0],
            [new \stdClass()],
            [null],
        ];
    }

    /**
     * @dataProvider getInvalidSplatValues
     * @depends testSplat
     * @expectedException \Icicle\Exception\UnexpectedTypeError
     *
     * @param mixed $value
     */
    public function testSplatWithNonArray($value)
    {
        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
        });

        $observable = $emitter->splat($this->createCallback(0));

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @dataProvider getInvalidSplatValues
     * @depends testSplat
     * @expectedException \Icicle\Exception\UnexpectedTypeError
     *
     * @param mixed $value
     */
    public function testSplatWithNonArrayReturn($value)
    {
        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $value;
        });

        $observable = $emitter->splat($this->createCallback(0), $this->createCallback(0));

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testFilter
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testSplatErroredEmitter()
    {
        $value = [1, 2, 3];

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
            throw new EmitterTestException();
        });

        $observable = $emitter->splat($this->createCallback(1));

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     */
    public function testSkip()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
            yield $value;
        });

        $observable = $emitter->skip(3);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo(4));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testSkip
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testSkipWithInvalidCount()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->skip(-1);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     */
    public function testTake()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
        });

        $observable = $emitter->take(2);

        $callback = $this->createCallback(2);

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame(2, $awaitable->wait());

        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testTake
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testTakeWithInvalidCount()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->take(-1);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     */
    public function testThrottle()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
        });

        $observable = $emitter->throttle(self::TIMEOUT);

        $awaitable = new Coroutine($observable->each($this->createCallback(4)));

        $awaitable->done();

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT * 3);

        $this->assertTrue($emitter->isComplete());
        $this->assertTrue($awaitable->isFulfilled());
        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testEmit
     */
    public function testThrottleWithDelayedEmits()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(Awaitable\resolve(2)->delay(self::TIMEOUT * 2));
            yield $emit(3);
            yield $emit(Awaitable\resolve(4)->delay(self::TIMEOUT * 2));
        });

        $observable = $emitter->throttle(self::TIMEOUT);

        $awaitable = new Coroutine($observable->each($this->createCallback(4)));

        $awaitable->done();

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT * 5);

        $this->assertTrue($emitter->isComplete());
        $this->assertTrue($awaitable->isFulfilled());
        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testEmit
     */
    public function testThrottleWithInvalidTime()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
        });

        $observable = $emitter->throttle(-1);

        $awaitable = new Coroutine($observable->each($this->createCallback(4)));

        $awaitable->done();

        $this->assertRunTimeLessThan('Icicle\Loop\run', self::TIMEOUT);

        $this->assertTrue($emitter->isComplete());
        $this->assertTrue($awaitable->isFulfilled());
        $this->assertTrue($observable->isComplete());
    }

    /**
     * @depends testEmit
     */
    public function testReduce()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
        });

        $i = 0;
        $seed = 0;
        $callback = function ($carry, $value) use (&$i, &$seed) {
            $this->assertSame(++$i, $value);
            $this->assertSame($seed, $carry);
            $seed += $value;
            return $carry + $value;
        };

        $observable = $emitter->reduce($callback, $seed);

        $callback = $this->createCallback(4);
        $callback->method('__invoke')
            ->will($this->returnCallback(function ($value) use (&$seed) {
                $this->assertSame($seed, $value);
            }));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame(10, $awaitable->wait());
    }

    /**
     * @depends testReduce
     */
    public function testReduceReturnAwaitable()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
        });

        $callback = function ($carry, $value) {
            return Awaitable\resolve($carry + $value);
        };

        $observable = $emitter->reduce($callback, 0);

        $awaitable = new Coroutine($observable->each($this->createCallback(4)));

        $this->assertSame(10, $awaitable->wait());
    }

    /**
     * @depends testReduce
     */
    public function testReduceCoroutine()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            yield $emit(4);
        });

        $callback = function ($carry, $value) {
            yield $carry + $value;
        };

        $observable = $emitter->reduce($callback, 0);

        $awaitable = new Coroutine($observable->each($this->createCallback(4)));

        $this->assertSame(10, $awaitable->wait());
    }

    /**
     * @depends testReduce
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testReduceAccumulatorThrows()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit();
        });

        $observable = $emitter->reduce(function () {
            throw new EmitterTestException();
        }, 0);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testReduce
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testReduceErroredEmitter()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use ($value) {
            yield $emit($value);
            throw new EmitterTestException();
        });

        $observable = $emitter->reduce($this->createCallback(1));

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDispose()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
        });

        $emitter->dispose();

        Loop\run();

        $this->assertTrue($emitter->isFailed());

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testDisposeWithCustomException()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
        });

        $exception = new EmitterTestException();

        $emitter->dispose($exception);

        Loop\run();

        $this->assertTrue($emitter->isFailed());

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testDisposeWithCustomException
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testDisposeWithOnDisposedCallback()
    {
        $exception = new EmitterTestException();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($exception));

        $emitter = new Emitter(function (callable $emit) {
            yield $emit(new Delayed());
        }, $callback);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick();

        $emitter->dispose($exception);

        $awaitable->wait();
    }

    /**
     * @depends testDisposeWithOnDisposedCallback
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testDisposeWithOnDisposedCallbackThrowingException()
    {
        $exception = new EmitterTestException();

        $callback = function () use ($exception) {
            throw $exception;
        };

        $emitter = new Emitter(function (callable $emit) {
            yield $emit(new Delayed());
        }, $callback);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick();

        $emitter->dispose();

        $awaitable->wait();
    }

    /**
     * @depends testDisposeWithOnDisposedCallback
     */
    public function testDisposeWithOnDisposedReturnsAwaitable()
    {
        $callback = function () {
            return Awaitable\resolve()->delay(self::TIMEOUT);
        };

        $emitter = new Emitter(function (callable $emit) {
            yield $emit(new Delayed());
        }, $callback);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick();

        $emitter->dispose();

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT);
    }

    /**
     * @depends testDisposeWithOnDisposedReturnsAwaitable
     * @depends testDisposeWithOnDisposedCallbackThrowingException
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testDisposeWithOnDisposedReturnsRejectedAwaitable()
    {
        $exception = new EmitterTestException();

        $callback = function () use ($exception) {
            return Awaitable\reject($exception);
        };

        $emitter = new Emitter(function (callable $emit) {
            yield $emit(new Delayed());
        }, $callback);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick();

        $emitter->dispose();

        $awaitable->wait();
    }

    /**
     * @depends testDisposeWithOnDisposedCallback
     */
    public function testDisposeWithOnDisposedCoroutine()
    {
        $callback = function () {
            yield Awaitable\resolve()->delay(self::TIMEOUT);
        };

        $emitter = new Emitter(function (callable $emit) {
            yield $emit(new Delayed());
        }, $callback);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick();

        $emitter->dispose();

        $this->assertRunTimeGreaterThan('Icicle\Loop\run', self::TIMEOUT);
    }

    /**
     * @depends testDisposeWithOnDisposedCoroutine
     * @depends testDisposeWithOnDisposedCallbackThrowingException
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testDisposeWithOnDisposedRejectedCoroutine()
    {
        $callback = function () {
            throw new EmitterTestException();
            yield; // Unreachable, but makes function a coroutine.
        };

        $emitter = new Emitter(function (callable $emit) {
            yield $emit(new Delayed());
        }, $callback);

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick();

        $emitter->dispose();

        $awaitable->wait();
    }

    /**
     * @depends testTake
     * @depends testDispose
     * @expectedException \Icicle\Observable\Exception\AutoDisposedException
     */
    public function testAutoDispose()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $iterator = $emitter->getIterator();

        Loop\tick();

        unset($iterator); // Destroys only listener.

        Loop\run();

        $this->assertTrue($emitter->isFailed());

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        $awaitable->wait();
    }
    /**
     * @depends testMap
     * @depends testDispose
     *
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposeFailsObservableFromMap()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->map($this->createCallback(0));

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testMap
     * @depends testDispose
     *
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposeFailsObservableFromSplat()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->splat($this->createCallback(0));

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testFilter
     * @depends testDispose
     *
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposeFailsObservableFromFilter()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->filter($this->createCallback(0));

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testTake
     * @depends testDispose
     *
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposeFailsObservableFromTake()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->take(5);

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testSkip
     * @depends testDispose
     *
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposeFailsObservableFromSkip()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->skip(5);

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        Loop\run();

        $awaitable->wait();
    }

    /**
     * @depends testThrottle
     * @depends testDispose
     *
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDisposeFailsObservableFromThrottle()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield $emit(1);
        });

        $observable = $emitter->throttle(1);

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }
}
