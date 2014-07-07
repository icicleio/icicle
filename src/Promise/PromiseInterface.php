<?php
namespace Icicle\Promise;

use Exception;

interface PromiseInterface
{
    /**
     * Assigns a set of callback functions to the promise, and returns a new promise.
     *
     * @param   callable|null $onFulfilled (mixed $value) : mixed
     * @param   callable|null $onRejected (Exception $exception) : mixed
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Assigns a set of callback functions to the promise. Returned values are ignored and thrown exceptions
     * are rethrown in an uncatchable way.
     *
     * @param   callable|null $onFulfilled (mixed $value) : mixed
     * @param   callable|null $onRejected (Exception $exception) : mixed
     *
     * @api
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Cancels the promise, signaling that the value is no longer needed. This method should call any appropriate
     * cancellation handler, then reject the promise with the given exception or a CancelledException if non is
     * given.
     *
     * @param   Exception|null $exception
     *
     * @api
     */
    public function cancel(Exception $exception = null);
    
    /**
     * Returns a promise that is rejected in $timeout seconds if the promise is not resolved before that time.
     * When the promise resolves, the returned promise is fulfilled or rejected with the same value.
     *
     * @param   float $timeout
     * @param   Exception|null $exception
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function timeout($timeout, Exception $exception = null);
    
    /**
     * Returns a promise that is fulfilled $time seconds after this promise is fulfilled. If the promise is rejected,
     * the returned promise is immediately rejected.
     *
     * @param   float $time
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function delay($time);
    
    /**
     * Returns a promise that if cancelled does not cancel this promise. The promise is resolved in the same way as
     * this promise.
     *
     * @param   callable|null $onCancelled
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function fork(callable $onCancelled = null);
    
    /**
     * Returns a promise that cannot be cancelled (cancel() is a no-op).
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function uncancellable();
    
    /**
     * Assigns a callback function to be called if the promise is fulfilled or rejected.
     * Shortcut to calling PromiseInterface::then($onResolved, $onResolved)
     *
     * @param   callable $onResolved
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function always(callable $onResolved);
    
    /**
     * Assigns a callback function that is called when the promise is rejected. The $typeFilter parameter may be
     * used to only handle certain types of exceptions. $typeFilter should be a function accepting an Exception
     * and returning a boolean, indicating if $onRejected should be called or if the exception should be re-thrown.
     *
     * @param   callable $onRejected
     * @param   callable|null $typeFilter
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function capture(callable $onRejected, callable $typeFilter = null);
    
    /**
     * Assigns a callback to be called if the promise is fulfilled or rejected. Returned values are ignored and
     * thrown exceptions are rethrown in an uncatchable way.
     * Shortcut to calling PromiseInterface::done($onResolved, $onResolved)
     *
     * @param   callable $onResolved
     *
     * @api
     */
    public function after(callable $onResolved);
    
    /**
     * Assigns a callback function that is called when the promise is rejected. The callback function's return
     * value is ignored and thrown exceptions are rethrown in an uncatchable way.
     * Shortcut to calling PromiseInterface::done(null, $onRejected)
     *
     * @param   callable $onRejected
     *
     * @api
     */
    public function otherwise(callable $onRejected);
    
    /**
     * Calls the given function with the value used to fulfill the promise, then fulfills the returned promise with
     * the same value. If the promise is rejected, the returned promise is also rejected and $onFulfilled is not called.
     * If $onFulfilled throws, the returned promise is rejected with the thrown exception. The return value of
     * $onFulfilled is not used.
     *
     * @param   callable $onFulfilled
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function tap(callable $onFulfilled);
    
    /**
     * The callback given to this function will be called if the promise is fulfilled or rejected. The callback is
     * called with no arguments. If the callback does not throw, the returned promise is resolved in the same way as
     * the original promise. That is, it is fulfilled or rejected with the same value or exception. If the callback
     * throws an exception, the returned promise is rejected with that exception.
     *
     * @param   callable $onResolved
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public function cleanup(callable $onResolved);
    
    /**
     * Returns true if the promise has not been resolved.
     *
     * @return  bool
     *
     * @api
     */
    public function isPending();
    
    /**
     * Returns true if the promise has been fulfilled.
     *
     * @return bool
     *
     * @api
     */
    public function isFulfilled();
    
    /**
     * Returns true if the promise has been rejected.
     *
     * @return bool
     *
     * @api
     */
    public function isRejected();
    
    /**
     * Returns the value of the fulfilled or rejected promise if it has been resolved.
     *
     * @return  mixed
     *
     * @throws  UnresolvedException Thrown if the promise has not been resolved.
     *
     * @api
     */
    public function getResult();
}
