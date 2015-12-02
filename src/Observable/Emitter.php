<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

use Generator;
use Icicle\Coroutine\{Coroutine, function sleep};
use Icicle\Exception\{InvalidArgumentError, UnexpectedTypeError};
use Icicle\Awaitable\Awaitable;
use Icicle\Loop;
use Icicle\Observable\Exception\{DisposedException, InvalidEmitterError};
use Throwable;

class Emitter implements Observable
{
    /**
     * @var callable|null
     */
    private $emitter;

    /**
     * @var \Icicle\Coroutine\Coroutine|null
     */
    private $coroutine;

    /**
     * @var \Icicle\Observable\Internal\EmitQueue
     */
    private $queue;

    /**
     * @param callable $emitter
     */
    public function __construct(callable $emitter)
    {
        $this->emitter = $emitter;
        $this->queue = new Internal\EmitQueue($this);
    }

    /**
     * Executes the emitter coroutine.
     */
    private function start()
    {
        $emitter = $this->emitter;
        $this->emitter = null;

        Loop\queue(function () use ($emitter) { // Asynchronously start the observable.
            /**
             * Emits a value from the observable.
             *
             * @coroutine
             *
             * @param mixed $value If $value is an instance of \Icicle\Awaitable\Awaitable, the fulfillment value is
             *     used as the value to emit or the rejection reason is thrown from this coroutine. If $value is an
             *     instance of \Generator, it is used to create a coroutine which is then used as an awaitable.
             *
             * @return \Generator
             *
             * @resolve mixed The emitted value (the resolution value of $value)
             *
             * @throws \Icicle\Observable\Exception\CompletedError If the observable has been completed.
             * @throws \Icicle\Observable\Exception\BusyError If the observable is still busy emitting a value.
             */
            $emit = function ($value = null): \Generator {
                return $this->queue->push($value);
            };

            try {
                $generator = $emitter($emit);

                if (!$generator instanceof Generator) {
                    throw new UnexpectedTypeError('Generator', $generator);
                }

                $this->coroutine = new Coroutine($generator);
                $this->coroutine->done(
                    function ($value) {
                        $this->queue->complete($value);
                    },
                    function (Throwable $exception) {
                        $this->queue->fail($exception);
                    }
                );
            } catch (Throwable $exception) {
                $this->queue->fail(new InvalidEmitterError($emitter, $exception));
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function dispose(Throwable $exception = null)
    {
        if (null === $exception) {
            $exception = new DisposedException('Observable disposed.');
        }

        $this->emitter = null;

        if (null !== $this->coroutine) {
            $this->coroutine->cancel($exception);
        }

        $this->queue->fail($exception);
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ObservableIterator
    {
        if (null !== $this->emitter) {
            $this->start();
        }

        return new EmitterIterator($this->queue);
    }

    /**
     * {@inheritdoc}
     */
    public function each(callable $onNext = null): \Generator
    {
        $iterator = $this->getIterator();

        while (yield from $iterator->wait()) {
            if (null !== $onNext) {
                $result = $onNext($iterator->getCurrent());

                if ($result instanceof Generator) {
                    yield from $result;
                } elseif ($result instanceof Awaitable) {
                    yield $result;
                }
            }
        }

        return $iterator->getReturn();
    }

    /**
     * {@inheritdoc}
     */
    public function map(callable $onNext, callable $onComplete = null): Observable
    {
        return new self(function (callable $emit) use ($onNext, $onComplete) {
            $iterator = $this->getIterator();
            while (yield from $iterator->wait()) {
                yield from $emit($onNext($iterator->getCurrent()));
            }

            if (null === $onComplete) {
                return $iterator->getReturn();
            }

            $result = $onComplete($iterator->getReturn());

            if ($result instanceof Generator) {
                $result = yield from $result;
            } elseif ($result instanceof Awaitable) {
                $result = yield $result;
            }

            return $result;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function filter(callable $callback): Observable
    {
        return new self(function (callable $emit) use ($callback) {
            $iterator = $this->getIterator();
            while (yield from $iterator->wait()) {
                $value = $iterator->getCurrent();
                $result = $callback($value);

                if ($result instanceof Generator) {
                    $result = yield from $result;
                } elseif ($result instanceof Awaitable) {
                    $result = yield $result;
                }

                if ($result) {
                    yield from $emit($value);
                }
            }

            return $iterator->getReturn();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function throttle(float $time): Observable
    {
        if (0 >= $time) {
            return $this->skip(0);
        }

        return new self(function (callable $emit) use ($time) {
            $iterator = $this->getIterator();
            $start = microtime(true) - $time;

            while (yield from $iterator->wait()) {
                $value = $iterator->getCurrent();

                $diff = $time + $start - microtime(true);

                if (0 < $diff) {
                    yield from sleep($diff);
                }

                $start = microtime(true);

                yield from $emit($value);
            }

            return $iterator->getReturn();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function splat(callable $onNext, callable $onComplete = null): Observable
    {
        $onNext = function ($values) use ($onNext) {
            if ($values instanceof \Traversable) {
                $values = iterator_to_array($values);
            } elseif (!is_array($values)) {
                throw new UnexpectedTypeError('array or Traversable', $values);
            }

            ksort($values);
            return $onNext(...$values);
        };

        if (null !== $onComplete) {
            $onComplete = function ($values) use ($onComplete) {
                if ($values instanceof \Traversable) {
                    $values = iterator_to_array($values);
                } elseif (!is_array($values)) {
                    throw new UnexpectedTypeError('array or Traversable', $values);
                }

                ksort($values);
                return $onComplete(...$values);
            };
        }

        return $this->map($onNext, $onComplete);
    }

    /**
     * {@inheritdoc}
     */
    public function take(int $count): Observable
    {
        return new self(function (callable $emit) use ($count) {
            if (0 > $count) {
                throw new InvalidArgumentError('The number of values to take must be non-negative.');
            }

            $iterator = $this->getIterator();
            for ($i = 0; $i < $count && yield from $iterator->wait(); ++$i) {
                yield from $emit($iterator->getCurrent());
            }

            return $i;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function skip(int $count): Observable
    {
        return new self(function (callable $emit) use ($count) {
            if (0 > $count) {
                throw new InvalidArgumentError('The number of values to skip must be non-negative.');
            }

            $iterator = $this->getIterator();
            for ($i = 0; $i < $count && yield from $iterator->wait(); ++$i);
            while (yield from $iterator->wait()) {
                yield from $emit($iterator->getCurrent());
            }

            return $iterator->getReturn();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isComplete(): bool
    {
        return $this->queue->isComplete();
    }

    /**
     * {@inheritdoc}
     */
    public function isFailed(): bool
    {
        return $this->queue->isFailed();
    }
}
