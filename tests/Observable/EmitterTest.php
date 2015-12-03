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
            yield from $emit();
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
            yield from $emit($value);
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
            return yield from $emit($delayed);
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
    public function testEmitCoroutine()
    {
        $value = 1;

        $generator = function () use ($value) {
            return yield $value;
        };

        $emitter = new Emitter(function (callable $emit) use ($generator) {
            return yield from $emit($generator());
        });

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $awaitable = new Coroutine($emitter->each($callback));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($emitter->isComplete());
    }

    /**
     * @depends testEmit
     */
    public function testEmitBackPressure()
    {
        $value = 1;

        $emitter = new Emitter(function (callable $emit) use (&$time, $value) {
            $time = microtime(true);
            yield from $emit();
            $time = microtime(true) - $time;
            return $value;
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
     * @expectedException \Icicle\Observable\Exception\BusyError
     */
    public function testSimultaneousEmitThrows()
    {
        $emitter = new Emitter(function (callable $emit) {
            $coroutine1 = new Coroutine($emit(1));
            $coroutine2 = new Coroutine($emit(2));

            yield $coroutine1;
            yield $coroutine2;
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(1)));

        $awaitable->wait();
    }

    /**
     * @depends testEmit
     * @expectedException \Icicle\Tests\Observable\EmitterTestException
     */
    public function testEachCallbackThrowingRejectsCoroutine()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield from $emit();
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
            return yield from $emit($value);
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
            return yield from $emit($value);
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
            yield from $emit(1);
            yield from $emit(2);
            yield from $emit(3);
            yield from $emit(4);
            return $value;
        });

        $i = 0;

        $observable = $emitter->map(function ($value) use (&$i) {
            return yield $value++;
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
            yield from $emit();
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
            yield from $emit($value);
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
            yield from $emit(0);
            yield from $emit(1);
            yield from $emit(2);
            return $value;
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
            yield from $emit(0);
            yield from $emit(1);
            yield from $emit(2);
            return $value;
        });

        $callback = function ($value) {
            return yield 1 === $value;
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
            yield from $emit();
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
            yield from $emit($value);
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
            yield from $emit([1, 2, 3]);
            yield from $emit(new \ArrayIterator([1, 2, 3]));
            return $value;
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
            yield from $emit([1, 2, 3]);
            yield from $emit(new \ArrayIterator([1, 2, 3]));
            return $value;
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
            yield from $emit($value);
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
            yield from $emit($value);
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
            yield from $emit(1);
            yield from $emit(2);
            yield from $emit(3);
            yield from $emit(4);
            return $value;
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
            yield from $emit(1);
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
            yield from $emit(1);
            yield from $emit(2);
            yield from $emit(3);
            yield from $emit(4);
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
            yield from $emit(1);
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
            yield from $emit(1);
            yield from $emit(2);
            yield from $emit(3);
            yield from $emit(4);
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
            yield from $emit(1);
            yield from $emit(Awaitable\resolve(2)->delay(self::TIMEOUT * 2));
            yield from $emit(3);
            yield from $emit(Awaitable\resolve(4)->delay(self::TIMEOUT * 2));
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
            yield from $emit(1);
            yield from $emit(2);
            yield from $emit(3);
            yield from $emit(4);
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
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testDispose()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield from $emit(1);
            yield from $emit(2);
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
            yield from $emit(1);
            yield from $emit(2);
        });

        $exception = new EmitterTestException();

        $emitter->dispose($exception);

        Loop\run();

        $this->assertTrue($emitter->isFailed());

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testTake
     * @depends testDispose
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testAutoDispose()
    {
        $emitter = new Emitter(function (callable $emit) {
            yield from $emit(1);
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
            yield from $emit(1);
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
            yield from $emit(1);
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
            yield from $emit(1);
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
            yield from $emit(1);
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
            yield from $emit(1);
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
            yield from $emit(1);
        });

        $observable = $emitter->throttle(1);

        $emitter->dispose();

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testEmitterCatchesDisposalException()
    {
        $emitter = new Emitter(function (callable $emit) {
            try {
                yield $emit(new Delayed());
            } catch (DisposedException $exception) {
                yield $emit(1); // Should throw again.
            }

            $this->fail('Emitting after disposal should throw.');
        });

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        Loop\tick(false);

        $emitter->dispose();

        $awaitable->wait();
    }
}
