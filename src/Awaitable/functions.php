<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using awaitables and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

use Exception;
use Icicle\Awaitable\Exception\MultiReasonException;
use Icicle\Exception\InvalidArgumentError;

if (!function_exists(__NAMESPACE__ . '\resolve')) {
    /**
     * Return a awaitable using the given value. There are two possible outcomes depending on the type of $value:
     * (1) Awaitable: The awaitable is returned without modification.
     * (2) All other types: A fulfilled awaitable is returned using the given value as the result.
     *
     * @param mixed $value
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function resolve($value = null)
    {
        if ($value instanceof Awaitable) {
            return $value;
        }

        return new Internal\FulfilledAwaitable($value);
    }
    
    /**
     * Create a new rejected awaitable using the given exception.
     *
     * @param \Exception $reason
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function reject(\Exception $reason)
    {
        return new Internal\RejectedAwaitable($reason);
    }
    
    /**
     * Wraps the given callable $worker in a awaitable aware function that has the same number of arguments as $worker,
     * but those arguments may be awaitables for the future argument value or just values. The returned function will
     * return a awaitable for the return value of $worker and will never throw. The $worker function will not be called
     * until each awaitable given as an argument is fulfilled. If any awaitable provided as an argument rejects, the
     * awaitable returned by the returned function will be rejected for the same reason. The awaitable is fulfilled with
     * the return value of $worker or rejected if $worker throws.
     *
     * @param callable $worker
     *
     * @return callable
     */
    function lift(callable $worker)
    {
        /**
         * @param mixed ...$args Awaitables or values.
         *
         * @return \Icicle\Awaitable\Awaitable
         */
        return function (/* ...$args */) use ($worker) {
            $args = func_get_args();

            if (1 === count($args)) {
                return resolve($args[0])->then($worker);
            }

            return all($args)->splat($worker);
        };
    }
    
    /**
     * Transforms a function that takes a callback into a function that returns a awaitable. The awaitable is fulfilled
     * with an array of the parameters that would have been passed to the callback function.
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

            return new Promise(function (callable $resolve) use ($worker, $index, $args) {
                $callback = function (/* ...$args */) use ($resolve) {
                    $resolve(func_get_args());
                };

                if (count($args) < $index) {
                    throw new InvalidArgumentError('Too few arguments given to function.');
                }

                array_splice($args, $index, 0, [$callback]);

                call_user_func_array($worker, $args);
            });
        };
    }

    /**
     * Adapts any object with a then(callable $onFulfilled, callable $onRejected) method to a awaitable usable by
     * components depending on awaitables implementing Awaitable.
     *
     * @param object $thenable Object with a then() method.
     *
     * @return Awaitable Promise resolved by the $thenable object.
     */
    function adapt($thenable)
    {
        if (!is_object($thenable) || !method_exists($thenable, 'then')) {
            return reject(new InvalidArgumentError('Must provide an object with a then() method.'));
        }

        return new Promise(function (callable $resolve, callable $reject) use ($thenable) {
            $thenable->then($resolve, $reject);
        });
    }

    /**
     * Returns a awaitable that calls $promisor only when the result of the awaitable is requested (e.g., then() or
     * done() is called on the returned awaitable). $promisor can return a awaitable or any value. If $promisor throws
     * an exception, the returned awaitable is rejected with that exception.
     *
     * @param callable $promisor
     * @param mixed ...$args
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function lazy(callable $promisor /* ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        if (empty($args)) {
            return new Internal\LazyAwaitable($promisor);
        }

        return new Internal\LazyAwaitable(function () use ($promisor, $args) {
            return call_user_func_array($promisor, $args);
        });
    }

    /**
     * Returns a awaitable that is resolved when all awaitables are resolved. The returned awaitable will not reject by
     * itself (only if cancelled). Returned awaitable is fulfilled with an array of resolved awaitables, with keys
     * identical and corresponding to the original given array.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function settle(array $awaitables)
    {
        if (empty($awaitables)) {
            return resolve([]);
        }

        return new Promise(function (callable $resolve) use ($awaitables) {
            $pending = count($awaitables);

            $after = function () use (&$awaitables, &$pending, $resolve) {
                if (0 === --$pending) {
                    $resolve($awaitables);
                }
            };

            foreach ($awaitables as &$awaitable) {
                $awaitable = resolve($awaitable);
                $awaitable->done($after, $after);
            }
        });
    }
    
    /**
     * Returns a awaitable that is fulfilled when all awaitables are fulfilled, and rejected if any awaitable is
     * rejected. Returned awaitable is fulfilled with an array of values used to fulfill each contained awaitable, with
     * keys corresponding to the array of awaitables.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function all(array $awaitables)
    {
        if (empty($awaitables)) {
            return resolve([]);
        }

        return new Promise(function (callable $resolve, callable $reject) use ($awaitables) {
            $pending = count($awaitables);
            $values = [];

            foreach ($awaitables as $key => $awaitable) {
                $onFulfilled = function ($value) use ($key, &$values, &$pending, $resolve) {
                    $values[$key] = $value;
                    if (0 === --$pending) {
                        $resolve($values);
                    }
                };

                resolve($awaitable)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Returns a awaitable that is fulfilled when any awaitable is fulfilled, and rejected only if all awaitables are
     * rejected.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function any(array $awaitables)
    {
        if (empty($awaitables)) {
            return reject(new InvalidArgumentError('No awaitables provided.'));
        }

        return new Promise(function (callable $resolve, callable $reject) use ($awaitables) {
            $pending = count($awaitables);
            $exceptions = [];

            foreach ($awaitables as $key => $awaitable) {
                $onRejected = function (Exception $exception) use ($key, &$exceptions, &$pending, $reject) {
                    $exceptions[$key] = $exception;
                    if (0 === --$pending) {
                        $reject(new MultiReasonException($exceptions));
                    }
                };

                resolve($awaitable)->done($resolve, $onRejected);
            }
        });
    }
    
    /**
     * Returns a awaitable that is fulfilled when $required number of awaitables are fulfilled. The awaitable is
     * rejected if $required number of awaitables can no longer be fulfilled.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     * @param int $required Number of awaitables that must be fulfilled to fulfill the returned awaitable.
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function some(array $awaitables, $required)
    {
        $required = (int) $required;

        if (0 >= $required) {
            return resolve([]);
        }

        if ($required > count($awaitables)) {
            return reject(new InvalidArgumentError('Too few awaitables provided.'));
        }

        return new Promise(function (callable $resolve, callable $reject) use ($awaitables, $required) {
            $pending = count($awaitables);
            $required = min($pending, $required);
            $values = [];
            $exceptions = [];

            foreach ($awaitables as $key => $awaitable) {
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

                resolve($awaitable)->done($onFulfilled, $onRejected);
            }
        });
    }
    
    /**
     * Returns a awaitable that is fulfilled or rejected when the first awaitable is fulfilled or rejected.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function choose(array $awaitables)
    {
        if (empty($awaitables)) {
            return reject(new InvalidArgumentError('No awaitables provided.'));
        }

        return new Promise(function (callable $resolve, callable $reject) use ($awaitables) {
            foreach ($awaitables as $awaitable) {
                resolve($awaitable)->done($resolve, $reject);
            }
        });
    }
    
    /**
     * Maps the callback to each awaitable as it is fulfilled. Returns an array of awaitables resolved by the return
     * callback value of the callback function. The callback may return awaitables or throw exceptions to reject
     * awaitables in the array. If a awaitable in the passed array rejects, the callback will not be called and the
     * awaitable in the array is rejected for the same reason. Tip: Use all() or settle() to determine when all
     * awaitables in the array have been resolved.
     *
     * @param callable(mixed $value): mixed $callback
     * @param mixed[] ...$awaitables Awaitables or values (passed through resolve() to create awaitables).
     *
     * @return \Icicle\Awaitable\Awaitable[] Array of awaitables resolved with the result of the mapped function.
     */
    function map(callable $callback /* array ...$awaitables */)
    {
        $args = func_get_args();
        $args[0] = lift($args[0]);

        return call_user_func_array('array_map', $args);
    }
    
    /**
     * Reduce function similar to array_reduce(), only it works on awaitables and/or values. The callback function may
     * return a awaitable or value and the initial value may also be a awaitable or value.
     *
     * @param mixed[] $awaitables Awaitables or values (passed through resolve() to create awaitables).
     * @param callable(mixed $carry, mixed $value): mixed Called for each fulfilled awaitable value.
     * @param mixed $initial The initial value supplied for the $carry parameter of the callback function.
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    function reduce(array $awaitables, callable $callback, $initial = null)
    {
        if (empty($awaitables)) {
            return resolve($initial);
        }

        return new Promise(function (callable $resolve, callable $reject) use ($awaitables, $callback, $initial) {
            $pending = true;
            $count = count($awaitables);
            $carry = resolve($initial);
            $carry->done(null, $reject);

            $onFulfilled = function ($value) use (&$carry, &$pending, &$count, $callback, $resolve, $reject) {
                if ($pending) {
                    $carry = $carry->then(function ($carry) use ($callback, $value) {
                        return $callback($carry, $value);
                    });
                    $carry->done(null, $reject);

                    if (0 === --$count) {
                        $resolve($carry);
                    }
                }
            };

            foreach ($awaitables as $awaitable) {
                resolve($awaitable)->done($onFulfilled, $reject);
            }

            return function (Exception $exception) use (&$carry, &$pending) {
                $pending = false;
                $carry->cancel($exception);
            };
        });
    }
}
