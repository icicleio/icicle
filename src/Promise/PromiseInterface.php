<?php
namespace Icicle\Promise;

interface PromiseInterface
{
    /**
     * Assigns a set of callback functions to the promise, and returns a new promise.
     *
     * @param   callable|null $onFulfilled (mixed $value) : mixed
     * @param   callable|null $onRejected (Exception $exception) : mixed
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Assigns a set of callback functions to the promise. Returned values are ignored and thrown exceptions
     * are rethrown in an uncatchable way.
     *
     * @param   callable|null $onFulfilled (mixed $value) : mixed
     * @param   callable|null $onRejected (Exception $exception) : mixed
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * Cancels the promise, signaling that the value is no longer needed. This method should call any appropriate
     * cancellation handler, then reject the promise with the given exception or a CancelledException if none is
     * given.
     *
     * @param   mixed $reason
     */
    public function cancel($reason = null);
    
    /**
     * Cancels the promise with $reason if it is not resolved within $timeout seconds. When the promise resolves, the
     * returned promise is fulfilled or rejected with the same value.
     *
     * @param   float $timeout
     * @param   mixed $reason
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function timeout($timeout, $reason = null);
    
    /**
     * Returns a promise that is fulfilled $time seconds after this promise is fulfilled. If the promise is rejected,
     * the returned promise is immediately rejected.
     *
     * @param   float $time
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function delay($time);

    /**
     * Assigns a callback function that is called when the promise is rejected. If a typehint is defined on the callable
     * (ex: function (RuntimeException $exception) {}), then the function will only be called if the exception is an
     * instance of the typehinted exception.
     *
     * @param   callable $onRejected
     *
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function capture(callable $onRejected);

    /**
     * Calls the given function with the value used to fulfill the promise, then fulfills the returned promise with
     * the same value. If the promise is rejected, the returned promise is also rejected and $onFulfilled is not called.
     * If $onFulfilled throws, the returned promise is rejected with the thrown exception. The return value of
     * $onFulfilled is not used.
     *
     * @param   callable $onFulfilled
     *
     * @return  \Icicle\Promise\PromiseInterface
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
     * @return  \Icicle\Promise\PromiseInterface
     */
    public function cleanup(callable $onResolved);

    /**
     * If the promises returns an array or a Traversable object, this function use the array (or array generated from
     * traversing the iterator) as arguments to the given function. The array is key sorted before used as arguments.
     * If the promise does not return an array, the returned promise will be rejected with an
     * \Icicle\Promise\Exception\InvalidArgumentException.
     *
     * @param   callable $onFulfilled
     *
     * @return  mixed
     */
    public function splat(callable $onFulfilled);
    
    /**
     * Returns true if the promise has not been resolved.
     *
     * @return  bool
     */
    public function isPending();
    
    /**
     * Returns true if the promise has been fulfilled.
     *
     * @return bool
     */
    public function isFulfilled();
    
    /**
     * Returns true if the promise has been rejected.
     *
     * @return bool
     */
    public function isRejected();
    
    /**
     * Returns the value of the fulfilled or rejected promise if it has been resolved.
     *
     * @return  mixed
     *
     * @throws  \Icicle\Promise\Exception\UnresolvedException If the promise has not been resolved.
     */
    public function getResult();
    
    /**
     * Iteratively finds the last promise in the pending chain and returns it. 
     *
     * @return  \Icicle\Promise\PromiseInterface
     *
     * @internal Used to keep promise methods from exceeding the call stack depth limit.
     */
    public function unwrap();
}
