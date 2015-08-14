<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise;

interface PromiseInterface
{
    /**
     * Assigns a set of callback functions to the promise, and returns a new promise.
     *
     * @param callable<(mixed $value): mixed>|null $onFulfilled
     * @param callable<(\Throwable $exception): mixed>|null $onRejected
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface;
    
    /**
     * Assigns a set of callback functions to the promise. Returned values are ignored and thrown exceptions
     * are rethrown in an uncatchable way.
     *
     * @param callable<(mixed $value)>|null $onFulfilled
     * @param callable<(\Throwable $exception)>|null $onRejected
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Cancels the promise, signaling that the value is no longer needed. This method should call any appropriate
     * cancellation handler, then reject the promise with the given exception or a CancelledException if none is
     * given.
     *
     * @param mixed $reason
     */
    public function cancel($reason = null);
    
    /**
     * Cancels the promise with $reason if it is not resolved within $timeout seconds. When the promise resolves, the
     * returned promise is fulfilled or rejected with the same value.
     *
     * @param float $timeout
     * @param mixed $reason
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function timeout(float $timeout, $reason = null): PromiseInterface;
    
    /**
     * Returns a promise that is fulfilled $time seconds after this promise is fulfilled. If the promise is rejected,
     * the returned promise is immediately rejected.
     *
     * @param float $time
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function delay(float $time): PromiseInterface;

    /**
     * Assigns a callback function that is called when the promise is rejected. If a typehint is defined on the callable
     * (ex: function (RuntimeException $exception) {}), then the function will only be called if the exception is an
     * instance of the typehinted exception.
     *
     * @param callable<(\Throwable $exception): mixed)> $onRejected
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function capture(callable $onRejected): PromiseInterface;

    /**
     * Calls the given function with the value used to fulfill the promise, then fulfills the returned promise with
     * the same value. If the promise is rejected, the returned promise is also rejected and $onFulfilled is not called.
     * If $onFulfilled throws, the returned promise is rejected with the thrown exception. The return value of
     * $onFulfilled is not used.
     *
     * @param callable<(): PromiseInterface|null)> $onFulfilled
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function tap(callable $onFulfilled): PromiseInterface;
    
    /**
     * The callback given to this function will be called if the promise is fulfilled or rejected. The callback is
     * called with no arguments. If the callback does not throw, the returned promise is resolved in the same way as
     * the original promise. That is, it is fulfilled or rejected with the same value or exception. If the callback
     * throws an exception, the returned promise is rejected with that exception.
     *
     * @param callable<(): PromiseInterface|null)> $onResolved
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function cleanup(callable $onResolved): PromiseInterface;

    /**
     * If the promise returns an array or a Traversable object, this function uses the array (or array generated from
     * traversing the iterator) as arguments to the given function. The array is key sorted before being used as
     * function arguments. If the promise does not return an array, the returned promise will be rejected with an
     * \Icicle\Promise\Exception\TypeException.
     *
     * @param callable<(mixed ...$args): mixed> $onFulfilled
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    public function splat(callable $onFulfilled);

    /**
     * This function may be used to synchronously wait for a promise to be resolved. This function should generally
     * not be used within a running event loop, but rather to set up a task (or set of tasks, then use join() or another
     * function to group them) and synchronously wait for the task to complete. Using this function in a running event
     * loop will not block the loop, but it will prevent control from moving past the call to this function and disrupt
     * program flow.
     *
     * @return mixed Promise fulfillment value.
     *
     * @throws \Icicle\Promise\Exception\UnresolvedError If the event loop empties without fulfilling the promise.
     * @throws \Throwable If the promise is rejected, the rejection reason is thrown from this function.
     */
    public function wait();

    /**
     * Returns true if the promise has not been resolved.
     *
     * @return bool
     */
    public function isPending(): bool;
    
    /**
     * Returns true if the promise has been fulfilled.
     *
     * @return bool
     */
    public function isFulfilled(): bool;
    
    /**
     * Returns true if the promise has been rejected.
     *
     * @return bool
     */
    public function isRejected(): bool;

    /**
     * Returns true if the promise has been cancelled.
     *
     * @return bool
     */
    public function isCancelled(): bool;

    /**
     * Iteratively finds the last promise in the pending chain and returns it. 
     *
     * @return \Icicle\Promise\PromiseInterface
     *
     * @internal Used to keep promise methods from exceeding the call stack depth limit.
     */
    public function unwrap(): PromiseInterface;
}
