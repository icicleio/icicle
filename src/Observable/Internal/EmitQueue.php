<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable\Internal;

use Icicle\Awaitable\{Awaitable, Delayed};
use Icicle\Observable\Exception\{AutoDisposedException, CompletedError};
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
     */
    public function push($value): \Generator
    {
        while ($this->busy) {
            if (null === $this->emitting) {
                $this->emitting = new Delayed();
            }

            yield $this->emitting; // Prevent simultaneous emit.
        }

        $this->busy = true;

        try {
            if ($value instanceof Awaitable) {
                $value = yield $value;
            }

            if (null === $this->observable) {
                throw new CompletedError();
            }

            $this->delayed->resolve($value);
            $this->delayed = new Delayed();

            $placeholder = $this->placeholder;
            $this->placeholder = new Placeholder($this->delayed);

            yield $placeholder->wait();
        } catch (\Throwable $exception) {
            $this->fail($exception);
            throw $exception;
        } finally {
            $this->busy = false;
            if (null !== $this->emitting) {
                $this->emitting->resolve();
                $this->emitting = null;
            }
        }

        return $value;
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
    public function pull(): Placeholder
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
     * @param \Throwable $exception
     */
    public function fail(\Throwable $exception)
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
    public function isComplete(): bool
    {
        return null === $this->observable;
    }

    /**
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->failed;
    }
}
