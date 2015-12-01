<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

use Exception;
use Generator;
use Icicle\Coroutine as CoroutineNS;
use Icicle\Coroutine\Coroutine;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Exception\UnexpectedTypeError;
use Icicle\Loop;
use Icicle\Observable\Exception\DisposedException;
use Icicle\Observable\Exception\InvalidEmitterError;

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
            $emit = function ($value = null) {
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
                    function (Exception $exception) {
                        $this->queue->fail($exception);
                    }
                );
            } catch (Exception $exception) {
                $this->queue->fail(new InvalidEmitterError($emitter, $exception));
            }
        });
    }

    /**
     * {@inheritdoc}
     */
    public function dispose(Exception $exception = null)
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
    public function getIterator()
    {
        if (null !== $this->emitter) {
            $this->start();
        }

        return new EmitterIterator($this->queue);
    }

    /**
     * {@inheritdoc}
     */
    public function each(callable $onNext = null)
    {
        $iterator = $this->getIterator();

        while (yield $iterator->wait()) {
            if (null !== $onNext) {
                yield $onNext($iterator->getCurrent());
            }
        }

        yield $iterator->getReturn();
    }

    /**
     * {@inheritdoc}
     */
    public function map(callable $onNext, callable $onComplete = null)
    {
        return new self(function (callable $emit) use ($onNext, $onComplete) {
            $iterator = $this->getIterator();
            while (yield $iterator->wait()) {
                yield $emit($onNext($iterator->getCurrent()));
            }

            if (null === $onComplete) {
                yield $iterator->getReturn();
                return;
            }

            yield $onComplete($iterator->getReturn());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function filter(callable $callback)
    {
        return new self(function (callable $emit) use ($callback) {
            $iterator = $this->getIterator();
            while (yield $iterator->wait()) {
                $value = $iterator->getCurrent();
                if (yield $callback($value)) {
                    yield $emit($value);
                }
            }

            yield $iterator->getReturn();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function throttle($time)
    {
        $time = (float) $time;
        if (0 >= $time) {
            return $this->skip(0);
        }

        return new self(function (callable $emit) use ($time) {
            $iterator = $this->getIterator();
            $start = microtime(true) - $time;

            while (yield $iterator->wait()) {
                $value = $iterator->getCurrent();

                $diff = $time + $start - microtime(true);

                if (0 < $diff) {
                    yield CoroutineNS\sleep($diff);
                }

                $start = microtime(true);

                yield $emit($value);
            }

            yield $iterator->getReturn();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function splat(callable $onNext, callable $onComplete = null)
    {
        $onNext = function ($values) use ($onNext) {
            if ($values instanceof \Traversable) {
                $values = iterator_to_array($values);
            } elseif (!is_array($values)) {
                throw new UnexpectedTypeError('array or Traversable', $values);
            }

            ksort($values);
            return call_user_func_array($onNext, $values);
        };

        if (null !== $onComplete) {
            $onComplete = function ($values) use ($onComplete) {
                if ($values instanceof \Traversable) {
                    $values = iterator_to_array($values);
                } elseif (!is_array($values)) {
                    throw new UnexpectedTypeError('array or Traversable', $values);
                }

                ksort($values);
                return call_user_func_array($onComplete, $values);
            };
        }

        return $this->map($onNext, $onComplete);
    }

    /**
     * {@inheritdoc}
     */
    public function take($count)
    {
        return new self(function (callable $emit) use ($count) {
            $count = (int) $count;
            if (0 > $count) {
                throw new InvalidArgumentError('The number of values to take must be non-negative.');
            }

            $iterator = $this->getIterator();
            for ($i = 0; $i < $count && (yield $iterator->wait()); ++$i) {
                yield $emit($iterator->getCurrent());
            }

            yield $i;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function skip($count)
    {
        return new self(function (callable $emit) use ($count) {
            $count = (int) $count;
            if (0 > $count) {
                throw new InvalidArgumentError('The number of values to skip must be non-negative.');
            }

            $iterator = $this->getIterator();
            for ($i = 0; $i < $count && (yield $iterator->wait()); ++$i);
            while (yield $iterator->wait()) {
                yield $emit($iterator->getCurrent());
            }

            yield $iterator->getReturn();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function isComplete()
    {
        return $this->queue->isComplete();
    }

    /**
     * {@inheritdoc}
     */
    public function isFailed()
    {
        return $this->queue->isFailed();
    }
}
