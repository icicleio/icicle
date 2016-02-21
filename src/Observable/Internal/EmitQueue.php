<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable\Internal;

use Icicle\Awaitable\Delayed;
use Icicle\Observable\Exception\AutoDisposedException;
use Icicle\Observable\Exception\CircularEmitError;
use Icicle\Observable\Exception\CompletedError;
use Icicle\Observable\Observable;

class EmitQueue
{
    /**
     * @var \Icicle\Observable\Observable|null
     */
    private $observable;

    /**
     * @var bool
     */
    private $busy = false;

    /**
     * @var bool
     */
    private $failed = false;

    /**
     * @var \Icicle\Awaitable\Delayed
     */
    private $delayed;

    /**
     * @var \Icicle\Awaitable\Delayed|null
     */
    private $emitting;

    /**
     * @var \Icicle\Observable\Internal\Placeholder
     */
    private $placeholder;

    /**
     * @var int Number of listening iterators.
     */
    private $listeners = 0;

    /**
     * @param \Icicle\Observable\Observable
     */
    public function __construct(Observable $observable)
    {
        $this->observable = $observable;
        $this->delayed = new Delayed();
        $this->placeholder = new Placeholder($this->delayed);
    }

    /**
     * @coroutine
     *
     * @param mixed $value
     *
     * @return \Generator
     *
     * @throws \Icicle\Observable\Exception\CompletedError
     * @throws \Icicle\Observable\Exception\CircularEmitError
     */
    public function push($value)
    {
        while ($this->busy) {
            if (null === $this->emitting) {
                $this->emitting = new Delayed();
            }

            yield $this->emitting; // Prevent simultaneous emit.
        }

        $this->busy = true;

        try {
            if ($value instanceof Observable) {
                if ($value === $this->observable) {
                    throw new CircularEmitError('Cannot emit an observable within itself.');
                }

                $iterator = $value->getIterator();

                while (yield $iterator->isValid()) {
                    yield $this->emit($iterator->getCurrent());
                }

                yield $iterator->getReturn();
                return;
            }

            yield $this->emit(yield $value);
        } catch (\Exception $exception) {
            $this->fail($exception);
            throw $exception;
        } finally {
            $this->busy = false;
            if (null !== $this->emitting) {
                $emitting = $this->emitting;
                $this->emitting = null;
                $emitting->resolve();
            }
        }

        yield $value;
    }

    /**
     * @param mixed $value Value to emit.
     *
     * @return \Icicle\Awaitable\Awaitable
     *
     * @throws \Icicle\Observable\Exception\CompletedError Thrown if the observable has completed.
     */
    private function emit($value)
    {
        if (null === $this->observable) {
            throw new CompletedError();
        }

        $delayed = $this->delayed;
        $placeholder = $this->placeholder;

        $this->delayed = new Delayed();
        $this->placeholder = new Placeholder($this->delayed);

        $delayed->resolve($value);

        return $placeholder->wait();
    }

    /**
     * Increments the number of listening iterators.
     */
    public function increment()
    {
        ++$this->listeners;
    }

    /**
     * Decrements the number of listening iterators. Marks the queue as disposed if the count reaches 0.
     */
    public function decrement()
    {
        if (0 >= --$this->listeners && null !== $this->observable) {
            $this->observable->dispose(new AutoDisposedException());
        }
    }

    /**
     * @return \Icicle\Observable\Internal\Placeholder
     */
    public function pull()
    {
        return $this->placeholder;
    }

    /**
     * Marks the observable as complete.
     *
     * @param mixed $value Observable return value.
     */
    public function complete($value)
    {
        if (null === $this->observable) {
            return;
        }

        $this->observable = null;
        $this->delayed->resolve($value);
    }

    /**
     * Marks the observable as complete with the given error.
     *
     * @param \Exception $exception
     */
    public function fail(\Exception $exception)
    {
        if (null === $this->observable) {
            return;
        }

        $this->observable = null;
        $this->failed = true;
        $this->delayed->reject($exception);
    }

    /**
     * @return bool
     */
    public function isComplete()
    {
        return null === $this->observable;
    }

    /**
     * @return bool
     */
    public function isFailed()
    {
        return $this->failed;
    }
}
