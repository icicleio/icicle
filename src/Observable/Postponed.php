<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

use Icicle\Awaitable\Delayed;
use Icicle\Observable\Exception\CompletedError;

final class Postponed
{
    /**
     * @var \Icicle\Observable\Emitter
     */
    private $emitter;

    /**
     * @var callable
     */
    private $emit;

    /**
     * @var \Icicle\Awaitable\Delayed
     */
    private $delayed;

    /**
     * @var \Icicle\Awaitable\Delayed|null
     */
    private $started;

    /**
     * @param callable|null $onDisposed
     */
    public function __construct(callable $onDisposed = null)
    {
        $this->started = new Delayed();
        $this->delayed = new Delayed();

        $this->emitter = new Emitter(function (callable $emit) {
            $this->started->resolve($emit);
            $this->started = null;

            yield $this->delayed;
        }, $onDisposed);
    }

    /**
     * @return \Icicle\Observable\Emitter
     */
    public function getEmitter()
    {
        return $this->emitter;
    }

    /**
     * Emits a value from the contained Emitter object.
     *
     * @coroutine
     *
     * @param mixed $value If $value is an instance of \Icicle\Awaitable\Awaitable, the fulfillment value is used
     *     as the value to emit or the rejection reason is thrown from this coroutine. If $value is an instance of
     *     \Generator, it is used to create a coroutine which is then used as an awaitable.
     *
     * @return \Generator
     *
     * @resolve mixed The emitted value (the resolution value of $value)
     *
     * @throws \Icicle\Observable\Exception\CompletedError If the observable has been completed.
     * @throws \Icicle\Observable\Exception\DisposedException If no listeners remain on the observable.
     */
    public function emit($value = null)
    {
        if (null === $this->emit) {
            $this->emit = (yield $this->started);
        }

        $emit = $this->emit;
        yield $emit($value);
    }

    /**
     * Completes the observable with the given value.
     *
     * @param mixed $value
     */
    public function complete($value = null)
    {
        if (null !== $this->started) {
            $this->started->reject(new CompletedError());
        }

        $this->delayed->resolve($value);
    }

    /**
     * Throws an error in the observable.
     *
     * @param \Exception $reason
     */
    public function fail(\Exception $reason)
    {
        if (null !== $this->started) {
            $this->started->reject(new CompletedError());
        }

        $this->delayed->reject($reason);
    }
}
