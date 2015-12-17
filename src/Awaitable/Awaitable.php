<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

interface Awaitable
{
    /**
     * Assigns a set of callback functions to the awaitable, and returns a new awaitable.
     *
     * @param callable(mixed $value): mixed|null $onFulfilled
     * @param callable(Exception $exception): mixed|null $onRejected
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Assigns a set of callback functions to the awaitable. Returned values are ignored and thrown exceptions
     * are rethrown in an uncatchable way.
     *
     * @param callable(mixed $value)|null $onFulfilled
     * @param callable(Exception $exception)|null $onRejected
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Cancels the awaitable, signaling that the value is no longer needed. This method should call any appropriate
     * cancellation handler, then reject the awaitable with the given exception or a CancelledException if none is
     * given.
     *
     * @param \Exception|null $reason If null, an instance of \Icicle\Awaitable\Exception\CancelledException is used.
     */
    public function cancel(\Exception $reason = null);
    
    /**
     * Invokes the given callback if the awaitable is not resolved within $timeout seconds. The awaitable returned from
     * this method is resolved by the return value or thrown exception from $onTimeout. If $onTimeout is null, the
     * returned awaitable will be rejected with an instance of \Icicle\Awaitable\Exception\TimeoutException.
     *
     * @param float $timeout
     * @param callable(): mixed|null $onTimeout
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function timeout($timeout, callable $onTimeout = null);
    
    /**
     * Returns an awaitable that is fulfilled $time seconds after this awaitable is fulfilled. If the promise is
     * rejected, the returned awaitable is immediately rejected.
     *
     * @param float $time
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function delay($time);

    /**
     * Assigns a callback function that is called when the awaitable is rejected. If a type declaration is defined on
     * the callable (ex: function (RuntimeException $exception) {}), then the function will only be called if the
     * exception is an instance of the declared type of exception.
     *
     * @param callable(Exception $exception): mixed) $onRejected
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function capture(callable $onRejected);

    /**
     * Calls the given function with the value used to fulfill the awaitable, then fulfills the returned awaitable with
     * the same value. If the awaitable is rejected, the returned awaitable is also rejected and $onFulfilled is not
     * called. If $onFulfilled throws, the returned awaitable is rejected with the thrown exception. The return value of
     * $onFulfilled is not used.
     *
     * @param callable(mixed $value): Awaitable|null) $onFulfilled
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function tap(callable $onFulfilled);
    
    /**
     * The callback given to this function will be called if the awaitable is fulfilled or rejected. The callback is
     * called with no arguments. If the callback does not throw, the returned awaitable is resolved in the same way as
     * the original awaitable. That is, it is fulfilled or rejected with the same value or exception. If the callback
     * throws an exception, the returned awaitable is rejected with that exception.
     *
     * @param callable(): Awaitable|null) $onResolved
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function cleanup(callable $onResolved);

    /**
     * If the awaitable returns an array or a Traversable object, this function uses the array (or array generated from
     * traversing the iterator) as arguments to the given function. The array is key sorted before being used as
     * function arguments. If the awaitable does not return an array, the returned awaitable will be rejected with an
     * \Icicle\Promise\Exception\UnexpectedTypeError.
     *
     * @param callable(mixed ...$args): mixed $onFulfilled
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function splat(callable $onFulfilled);

    /**
     * Returns an awaitable that will be resolved in the same way as this awaitable but cannot be cancelled.
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    public function uncancellable();

    /**
     * This function may be used to synchronously wait for an awaitable to be resolved. This function should generally
     * not be used within a running event loop, but rather to set up a task (or set of tasks, then use all() or another
     * function to group them) and synchronously wait for the task to complete. Using this function in a running event
     * loop will not block the loop, but it will prevent control from moving past the call to this function and disrupt
     * program flow.
     *
     * @return mixed Awaitable fulfillment value.
     *
     * @throws \Icicle\Awaitable\Exception\UnresolvedError If the event loop empties without fulfilling the awaitable.
     * @throws \Exception If the awaitable is rejected, the rejection reason is thrown from this function.
     */
    public function wait();

    /**
     * Returns true if the awaitable has not been resolved.
     *
     * @return bool
     */
    public function isPending();
    
    /**
     * Returns true if the awaitable has been fulfilled.
     *
     * @return bool
     */
    public function isFulfilled();
    
    /**
     * Returns true if the awaitable has been rejected.
     *
     * @return bool
     */
    public function isRejected();

    /**
     * Returns true if the awaitable has been cancelled.
     *
     * @return bool
     */
    public function isCancelled();
}
