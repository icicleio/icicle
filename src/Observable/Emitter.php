<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

use Generator;
use Icicle\Awaitable\{Awaitable, function resolve, function reject};
use Icicle\Coroutine\{Coroutine, function sleep};
use Icicle\Exception\{InvalidArgumentError, UnexpectedTypeError};
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
     * @var callable|null
     */
    private $onDisposed;

    /**
     * @param callable $emitter
     * @param callable|null $onDisposed Function invoked if the observable is disposed.
     */
    public function __construct(callable $emitter, callable $onDisposed = null)
    {
        $this->emitter = $emitter;
        $this->queue = new Internal\EmitQueue($this);
        $this->onDisposed = $onDisposed;
    }

    /**
     * Executes the emitter coroutine.
     */
    private function start()
    {
        Loop\queue(function () { // Asynchronously start the observable.
            if (null === $this->emitter) {
                return;
            }

            /**
             * Emits a value from the observable.
             *
             * @coroutine
             *
             * @param mixed $value If $value is an instance of \Icicle\Awaitable\Awaitable, the fulfillment value is
             *     used as the value to emit or the rejection reason is thrown from this coroutine.
             *
             * @return \Generator
             *
             * @resolve mixed The emitted value (the resolution value of $value)
             *
             * @throws \Icicle\Observable\Exception\CompletedError If the observable has been completed.
             */
            $emit = function ($value = null): \Generator {
                return $this->queue->push($value);
            };

            try {
                $generator = ($this->emitter)($emit);

                if (!$generator instanceof Generator) {
                    throw new UnexpectedTypeError('Generator', $generator);
                }

                $this->coroutine = new Coroutine($generator);
                $this->coroutine->done([$this->queue, 'complete'], [$this->queue, 'fail']);
            } catch (Throwable $exception) {
                $this->queue->fail(new InvalidEmitterError($this->emitter, $exception));
            }

            $this->emitter = null;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function dispose(Throwable $exception = null)
    {
        if (null === $exception) {
            $exception = new DisposedException();
        }

        $this->emitter = null;

        if (null === $this->coroutine) {
            $this->queue->fail($exception);
            return;
        }

        if (null !== $this->onDisposed) {
            try {
                $result = ($this->onDisposed)($exception);

                if ($result instanceof Generator) {
                    $awaitable = new Coroutine($result);
                } else {
                    $awaitable = resolve($result);
                }

                $awaitable = $awaitable->then(function () use ($exception) {
                    throw $exception;
                });
            } catch (Throwable $exception) {
                $awaitable = reject($exception);
            }
        } else {
            $awaitable = reject($exception);
        }

        $awaitable->done(null, [$this->coroutine, 'cancel']);
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

        while (yield from $iterator->isValid()) {
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
            while (yield from $iterator->isValid()) {
                $result = $onNext($iterator->getCurrent());

                if ($result instanceof Generator) {
                    $result = yield from $result;
                } elseif ($result instanceof Awaitable) {
                    $result = yield $result;
                }

                yield from $emit($result);
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
            while (yield from $iterator->isValid()) {
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

            while (yield from $iterator->isValid()) {
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
            for ($i = 0; $i < $count && yield from $iterator->isValid(); ++$i) {
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
            for ($i = 0; $i < $count && yield from $iterator->isValid(); ++$i);
            while (yield from $iterator->isValid()) {
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
