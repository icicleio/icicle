<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable\Internal;

use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Delayed;
use Icicle\Coroutine\Coroutine;
use Icicle\Observable\Exception\AutoDisposedException;
use Icicle\Observable\Exception\BusyError;
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
     * @param mixed $value
     *
     * @return \Generator
     *
     * @throws \Icicle\Observable\Exception\BusyError
     */
    public function push($value)
    {
        if ($this->busy) {
            throw new BusyError(
                'Still busy emitting the last value. Wait until the $emit coroutine has resolved.'
            );
        }

        $this->busy = true;

        if ($value instanceof \Generator) {
            $value = new Coroutine($value);
        }

        if ($value instanceof Awaitable) {
            $value = (yield $value);
        }

        $this->delayed->resolve($value);
        $this->delayed = new Delayed();

        $placeholder = $this->placeholder;
        $this->placeholder = new Placeholder($this->delayed);

        try {
            yield $placeholder->wait();
            yield $value;
        } finally {
            $this->busy = false;
        }
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
