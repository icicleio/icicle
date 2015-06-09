<?php
namespace Icicle\Promise;

use Exception;
use Icicle\Promise\Exception\LogicException;
use Icicle\Promise\Exception\MultiReasonException;
use Icicle\Promise\Exception\TypeException;
use Icicle\Promise\Structures\FulfilledPromise;
use Icicle\Promise\Structures\LazyPromise;
use Icicle\Promise\Structures\RejectedPromise;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    /**
     * Return a promise using the given value. There are two possible outcomes depending on the type of the passed value:
     * (1) PromiseInterface: The promise is returned without modification.
     * (2) All other types: A fulfilled promise is returned using the given value as the result.
     *
     * @param mixed $value
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function resolve($value = null)
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }

        return new FulfilledPromise($value);
    }
    
    /**
     * Create a new rejected promise using the given reason.
     *
     * @param mixed $reason
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function reject($reason = null)
    {
        return new RejectedPromise($reason);
    }
    
    /**
     * Wraps the given callable $worker in a promise aware function that takes the same number of arguments as $worker,
     * but those arguments may be promises for the future argument value or just values. The returned function will
     * return a promise for the return value of $worker and will never throw. The $worker function will not be called
     * until each promise given as an argument is fulfilled. If any promise provided as an argument rejects, the promise
     * returned by the returned function will be rejected for the same reason. The promise is fulfilled with the return
     * value of $worker or rejected if $worker throws.
     *
     * @param callable $worker
     *
     * @return callable
     */
    function lift(callable $worker)
    {
        /**
         * @param mixed ...$args Promises or values.
         *
         * @return \Icicle\Promise\PromiseInterface
         */
        return function (/* ...$args */) use ($worker) {
            return join(func_get_args())->splat($worker);
        };
    }
    
    /**
     * Transforms a function that takes a callback into a function that returns a promise. The promise is fulfilled with
     * an array of the parameters that would have been passed to the callback function.
     *
     * @param callable $worker Function that normally accepts a callback.
     * @param int $index Position of callback in $worker argument list (0-indexed).
     *
     * @return callable
     */
    function promisify(callable $worker, $index = 0)
    {
        return function (/* ...$args */) use ($worker, $index) {
            $args = func_get_args();

            return new Promise(function ($resolve) use ($worker, $index, $args) {
                $callback = function (/* ...$args */) use ($resolve) {
                    $resolve(func_get_args());
                };

                if (count($args) < $index) {
                    throw new LogicException('Too few arguments given to function.');
                }

                array_splice($args, $index, 0, [$callback]);

                call_user_func_array($worker, $args);
            });
        };
    }

    /**
     * Adapts any object with a then(callable $onFulfilled, callable $onRejected) method to a promise usable by
     * components depending on promises implementing PromiseInterface.
     *
     * @param object $thenable Object with a then() method.
     *
     * @return PromiseInterface Promise resolved by the $thenable object.
     */
    function adapt($thenable)
    {
        if (!is_object($thenable) || !method_exists($thenable, 'then')) {
            return reject(new TypeException('Must provide an object with a then() method.'));
        }

        return new Promise(function ($resolve, $reject) use ($thenable) {
            $thenable->then($resolve, $reject);
        });
    }

    /**
     * Returns a promise that calls $promisor only when the result of the promise is requested (e.g., then() or done()
     * is called on the returned promise). $promisor can return a promise or any value. If $promisor throws an
     * exception, the returned promise is rejected with that exception.
     *
     * @param callable $promisor
     * @param mixed ...$args
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function lazy(callable $promisor /* ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        if (empty($args)) {
            return new LazyPromise($promisor);
        }

        return new LazyPromise(function () use ($promisor, $args) {
            return call_user_func_array($promisor, $args);
        });
    }

    /**
     * Returns a promise that is resolved when all promises are resolved. The returned promise will not reject by itself
     * (only if cancelled). Returned promise is fulfilled with an array of resolved promises, with keys identical and
     * corresponding to the original given array.
     *
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function settle(array $promises)
    {
        if (empty($promises)) {
            return resolve([]);
        }

        return new Promise(function ($resolve) use ($promises) {
            $pending = count($promises);

            $after = function () use (&$promises, &$pending, $resolve) {
                if (0 === --$pending) {
                    $resolve($promises);
                }
            };

            foreach ($promises as &$promise) {
                $promise = resolve($promise);
                $promise->done($after, $after);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled when all promises are fulfilled, and rejected if any promise is rejected.
     * Returned promise is fulfilled with an array of values used to fulfill each contained promise, with keys
     * corresponding to the array of promises.
     *
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function join(array $promises)
    {
        if (empty($promises)) {
            return resolve([]);
        }

        return new Promise(function ($resolve, $reject) use ($promises) {
            $pending = count($promises);
            $values = [];

            foreach ($promises as $key => $promise) {
                $onFulfilled = function ($value) use ($key, &$values, &$pending, $resolve) {
                    $values[$key] = $value;
                    if (0 === --$pending) {
                        $resolve($values);
                    }
                };

                resolve($promise)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled when any promise is fulfilled, and rejected only if all promises are rejected.
     *
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function any(array $promises)
    {
        if (empty($promises)) {
            return reject(new LogicException('No promises provided.'));
        }

        return new Promise(function ($resolve, $reject) use ($promises) {
            $pending = count($promises);
            $exceptions = [];

            foreach ($promises as $key => $promise) {
                $onRejected = function (Exception $exception) use ($key, &$exceptions, &$pending, $reject) {
                    $exceptions[$key] = $exception;
                    if (0 === --$pending) {
                        $reject(new MultiReasonException($exceptions));
                    }
                };

                resolve($promise)->done($resolve, $onRejected);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled when $required number of promises are fulfilled. The promise is rejected if
     * $required number of promises can no longer be fulfilled.
     *
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     * @param int $required Number of promises that must be fulfilled to fulfill the returned promise.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function some(array $promises, $required)
    {
        $required = (int) $required;

        if (0 >= $required) {
            return resolve([]);
        }

        if ($required > count($promises)) {
            return reject(new LogicException('Too few promises provided.'));
        }

        return new Promise(function ($resolve, $reject) use ($promises, $required) {
            $pending = count($promises);
            $required = min($pending, $required);
            $values = [];
            $exceptions = [];

            foreach ($promises as $key => $promise) {
                $onFulfilled = function ($value) use ($key, &$values, &$pending, &$required, $resolve) {
                    $values[$key] = $value;
                    --$pending;
                    if (0 === --$required) {
                        $resolve($values);
                    }
                };

                $onRejected = function ($exception) use ($key, &$exceptions, &$pending, &$required, $reject) {
                    $exceptions[$key] = $exception;
                    if ($required > --$pending) {
                        $reject(new MultiReasonException($exceptions));
                    }
                };

                resolve($promise)->done($onFulfilled, $onRejected);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled or rejected when the first promise is fulfilled or rejected.
     *
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function choose(array $promises)
    {
        if (empty($promises)) {
            return reject(new LogicException('No promises provided.'));
        }

        return new Promise(function ($resolve, $reject) use ($promises) {
            foreach ($promises as $promise) {
                resolve($promise)->done($resolve, $reject);
            }
        });
    }
    
    /**
     * Maps the callback to each promise as it is fulfilled. Returns an array of promises resolved by the return
     * callback value of the callback function. The callback may return promises or throw exceptions to reject promises
     * in the array. If a promise in the passed array rejects, the callback will not be called and the promise in the
     * array is rejected for the same reason. Tip: Use join() or settle() method to determine when all promises in the
     * array have been resolved.
     *
     * @param callable<mixed (mixed $value)> $callback
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return \Icicle\Promise\PromiseInterface[] Array of promises resolved with the result of the mapped function.
     */
    function map(callable $callback, array $promises)
    {
        return array_map(function ($promise) use ($callback) {
            return resolve($promise)->then($callback);
        }, $promises);
    }
    
    /**
     * Reduce function similar to array_reduce(), only it works on promises and/or values. The callback function may
     * return a promise or value and the initial value may also be a promise or value.
     *
     * @param mixed[] $promises Promises or values (passed through resolve() to create promises).
     * @param callable $callback (mixed $carry, mixed $value) : mixed Called for each fulfilled promise value.
     * @param mixed $initial The initial value supplied for the $carry parameter of the callback function.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function reduce(array $promises, callable $callback, $initial = null)
    {
        if (empty($promises)) {
            return resolve($initial);
        }

        return $result = new Promise(function ($resolve, $reject) use (&$result, $promises, $callback, $initial) {
            $pending = count($promises);
            $carry = resolve($initial);
            $carry->done(null, $reject);

            $onFulfilled = function ($value) use (&$carry, &$result, &$pending, $callback, $resolve, $reject) {
                if ($result->isPending()) {
                    $carry = $carry->then(function ($carry) use ($callback, $value) {
                        return $callback($carry, $value);
                    });
                    $carry->done(null, $reject);

                    if (0 === --$pending) {
                        $resolve($carry);
                    }
                }
            };

            foreach ($promises as $promise) {
                resolve($promise)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Calls $worker using the return value of the previous call until $predicate returns false. $seed is used as the
     * initial parameter to $worker. $predicate is called before $worker with the value to be passed to $worker. If
     * $worker or $predicate throws an exception, the promise is rejected using that exception. The call stack is
     * cleared before each call to $worker to avoid filling the call stack. If $worker returns a promise, iteration
     * waits for the returned promise to be resolved.
     *
     * @param callable<mixed (mixed $value) $worker> Called with the previous return value on each iteration.
     * @param callable<bool (mixed $value) $predicate> Return false to stop iteration and fulfill promise.
     * @param mixed $seed Initial value given to $predicate and $worker (may be a promise).
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function iterate(callable $worker, callable $predicate, $seed = null)
    {
        return $result = new Promise(
            function ($resolve, $reject) use (&$result, &$promise, $worker, $predicate, $seed) {
                $callback = function ($value) use (
                    &$callback, &$result, &$promise, $worker, $predicate, $resolve, $reject
                ) {
                    if ($result->isPending()) {
                        try {
                            if (!$predicate($value)) { // Resolve promise if $predicate returns false.
                                $resolve($value);
                                return;
                            }
                            $promise = resolve($worker($value));
                            $promise->done($callback, $reject);
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    }
                };

                $promise = resolve($seed);
                $promise->done($callback, $reject); // Start iteration with $seed.
            },
            function (Exception $exception) use (&$promise) {
                $promise->cancel($exception);
            }
        );
    }
    
    /**
     * Repeatedly calls $promisor if the promise returned by $promisor is rejected or until $onRejected returns false.
     * Useful to retry an operation a number of times or until an operation fails with a specific exception.
     * If the promise returned by $promisor is fulfilled, the promise returned by this function is fulfilled with the
     * same value.
     *
     * @param callable<PromiseInterface ()> $promisor Performs an operation to be retried on failure.
     *     Should return a promise, but can return any type of value (will be made into a promise using resolve()).
     * @param callable<bool (Exception $exception) $onRejected> This function is called if the promise returned by
     *     $promisor is rejected. Returning true from this function will call $promiser again to retry the
     *     operation.
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    function retry(callable $promisor, callable $onRejected)
    {
        return $result = new Promise(
            function ($resolve, $reject) use (&$result, &$promise, $promisor, $onRejected) {
                $callback = function (Exception $exception) use (
                    &$callback, &$result, &$promise, $promisor, $onRejected, $resolve, $reject
                ) {
                    if ($result->isPending()) {
                        try {
                            if (!$onRejected($exception)) { // Reject promise if $onRejected returns false.
                                $reject($exception);
                                return;
                            }
                            $promise = resolve($promisor());
                            $promise->done($resolve, $callback);
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    }
                };

                $promise = resolve($promisor());
                $promise->done($resolve, $callback);
            },
            function (Exception $exception) use (&$promise) {
                $promise->cancel($exception);
            }
        );
    }
}
