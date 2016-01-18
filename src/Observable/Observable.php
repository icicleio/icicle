<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable;

interface Observable
{
    /**
     * @return \Icicle\Observable\ObservableIterator
     */
    public function getIterator();

    /**
     * Disposes of the observable, halting emission of values and failing the observable with the given exception.
     * If no exception is given, an instance of \Icicle\Observable\Exception\DisposedException is used.
     *
     * @param \Exception|null $exception
     */
    public function dispose(\Exception $exception = null);

    /**
     * The given callable will be invoked each time a value is emitted from the observable. The returned awaitable
     * will be fulfilled when the observable completes or rejected if an error occurs. The awaitable will also be
     * rejected if $onNext throws an exception or returns a rejected awaitable.
     *
     * @coroutine
     *
     * @param callable(mixed $value): \Generator|Awaitable|null|null $onNext
     *
     * @return \Generator
     *
     * @resolve mixed
     *
     * @throws \Exception Throws any exception thrown by the observable, used to dispose the observable, or thrown
     *     by the callback function given to this method.
     */
    public function each(callable $onNext = null);

    /**
     * Each emitted value is passed to $onNext. The value returned from $onNext is then emitted from the returned
     * observable. The return value of the observable is given to $onCompleted if provided. The return of $onCompleted
     * is the return value of observable returned from this method.
     *
     * @param callable(mixed $value): \Generator|Awaitable|mixed $onNext
     * @param callable(mixed $value): \Generator|Awaitable|mixed|null $onComplete
     *
     * @return \Icicle\Observable\Observable
     */
    public function map(callable $onNext, callable $onComplete = null);

    /**
     * Filters the values emitted by the observable using $callback. If $callback returns true, the value is emitted
     * from the returned observable. If $callback returns false, the value is ignored and not emitted.
     *
     * @param callable(mixed $value): \Generator|Awaitable|bool) $callback
     *
     * @return \Icicle\Observable\Observable
     */
    public function filter(callable $callback);

    /**
     * Reduce function similar to array_reduce(), instead invoking the accumulator as values are emitted. The initial
     * seed value may be any value or an awaitable. Each value returned from the accumulator is emitted from the
     * returned observable. The observable returns the final value returned from the accumulator.
     *
     * @param callable $accumulator
     * @param mixed $seed
     *
     * @return \Icicle\Observable\Observable
     */
    public function reduce(callable $accumulator, $seed = null);

    /**
     * Throttles the observable to only emit a value every $time seconds.
     *
     * @param float|int $time
     *
     * @return \Icicle\Observable\Observable
     */
    public function throttle($time);

    /**
     * This method is a modified form of map() that expects the observable to emit an array or Traversable that is
     * used as arguments to the given callback function. The array is key sorted before being used as function
     * arguments. If the observable does not emit an array or Traversable, the observable will error with an instance
     * of Icicle\Exception\UnexpectedTypeError.
     *
     * @param callable(mixed ...$args): mixed $onNext
     * @param callable(mixed ...$args): mixed|null $onComplete
     *
     * @return \Icicle\Observable\Observable
     */
    public function splat(callable $onNext, callable $onComplete = null);

    /**
     * Only emits the next $count values from the observable before completing.
     *
     * @param int $count
     *
     * @return \Icicle\Observable\Observable
     */
    public function take($count);

    /**
     * Skips the first $count values emitted from the observable.
     *
     * @param int $count
     *
     * @return \Icicle\Observable\Observable
     */
    public function skip($count);

    /**
     * Determines if the observable has completed (completed observables will no longer emit values). Returns true
     * even if the observable completed due to an error.
     *
     * @return bool
     */
    public function isComplete();

    /**
     * Determines if the observable has completed due to an error.
     *
     * @return bool
     */
    public function isFailed();
}
