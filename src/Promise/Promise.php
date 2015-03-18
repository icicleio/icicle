<?php
namespace Icicle\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Exception\CancelledException;
use Icicle\Promise\Exception\LogicException;
use Icicle\Promise\Exception\MultiReasonException;
use Icicle\Promise\Exception\RuntimeException;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Promise\Exception\TypeException;
use Icicle\Promise\Exception\UnresolvedException;
use Icicle\Promise\Structures\FulfilledPromise;
use Icicle\Promise\Structures\RejectedPromise;
use Icicle\Promise\Structures\ThenQueue;

class Promise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var PromiseInterface|null
     */
    private $result;
    
    /**
     * @var ThenQueue|null
     */
    private $onFulfilled;
    
    /**
     * @var ThenQueue|null
     */
    private $onRejected;
    
    /**
     * @var Closure|null
     */
    private $onCancelled;
    
    /**
     * @var int
     */
    private $children = 0;
    
    /**
     * @param   callable $resolver
     * @param   callable|null $onCancelled
     */
    public function __construct(callable $resolver, callable $onCancelled = null)
    {
        $this->onFulfilled = new ThenQueue();
        $this->onRejected = new ThenQueue();
        
        /**
         * Resolves the promise with the given promise or value. If another promise, this promise takes
         * on the state of that promise. If a value, the promise will be fulfilled with that value.
         *
         * @param   mixed $value A promise can be resolved with anything other than itself.
         */
        $resolve = function ($value = null) {
            if (null === $this->result) {
                if ($value instanceof PromiseInterface) {
                    $this->result = $value->unwrap();
                } else {
                    $this->result = new FulfilledPromise($value);
                }
                
                if ($this === $this->result) {
                    $this->result = new RejectedPromise(new TypeException('Circular reference in promise resolution.'));
                }
                
                $this->result->done($this->onFulfilled, $this->onRejected);
                
                $this->close();
            }
        };
        
        /**
         * Rejects the promise with the given exception.
         *
         * @param   mixed $reason
         */
        $reject = function ($reason = null) {
            if (null === $this->result) {
                $this->result = new RejectedPromise($reason);
                $this->result->done($this->onFulfilled, $this->onRejected);
                
                $this->close();
            }
        };
        
        if (null !== $onCancelled) {
            $this->onCancelled = function (Exception $exception) use ($reject, $onCancelled) {
                try {
                    $onCancelled($exception);
                } catch (Exception $exception) {
                    // Caught exception will now be used to reject promise.
                }
                
                $reject($exception);
            };
        } else {
            $this->onCancelled = $reject;
        }
        
        try {
            $resolver($resolve, $reject);
        } catch (Exception $exception) {
            $reject($exception);
        }
    }
    
    /**
     * The garbage collector does not automatically detect the deep circular references that can be
     * created, so explicitly setting these parameters to null is necessary for proper freeing of memory.
     */
    private function close()
    {
        $this->onFulfilled = null;
        $this->onRejected = null;
        $this->onCancelled = null;
    }
    
    /**
     * @inheritdoc
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            return $this->unwrap()->then($onFulfilled, $onRejected);
        }
        
        ++$this->children;
        
        return new static(
            function ($resolve, $reject) use ($onFulfilled, $onRejected) {
                if (null !== $onFulfilled) {
                    $this->onFulfilled->push(function ($value) use ($resolve, $reject, $onFulfilled) {
                        try {
                            $resolve($onFulfilled($value));
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    });
                } else {
                    $this->onFulfilled->push(function () use ($resolve) {
                        $resolve($this->result);
                    });
                }
                
                if (null !== $onRejected) {
                    $this->onRejected->push(function (Exception $exception) use ($resolve, $reject, $onRejected) {
                        try {
                            $resolve($onRejected($exception));
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    });
                } else {
                    $this->onRejected->push(function () use ($resolve) {
                        $resolve($this->result);
                    });
                }
            },
            function (Exception $exception) {
                Loop::schedule(function () use ($exception) {
                    if (0 === --$this->children) {
                        $this->cancel($exception);
                    }
                });
            }
        );
    }
    
    /**
     * @inheritdoc
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            $this->unwrap()->done($onFulfilled, $onRejected);
        } else {
            if (null !== $onFulfilled) {
                $this->onFulfilled->push($onFulfilled);
            }
            
            if (null !== $onRejected) {
                $this->onRejected->push($onRejected);
            } else {
                $this->onRejected->push(function (Exception $exception) {
                    throw $exception; // Rethrow exception in uncatchable way.
                });
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function cancel($reason = null)
    {
        if (null !== $this->result) {
            $this->unwrap()->cancel($reason);
        } else {
            if (!$reason instanceof Exception) {
                $reason = new CancelledException($reason);
            }
            
            $onCancelled = $this->onCancelled;
            $onCancelled($reason);
        }
    }
    
    /**
     * @inheritdoc
     */
    public function timeout($timeout, $reason = null)
    {
        if (null !== $this->result) {
            return $this->unwrap()->timeout($timeout, $reason);
        }
        
        ++$this->children;
        
        return new static(
            function ($resolve) use (&$timer, $timeout, $reason) {
                $timer = Loop::timer($timeout, function () use ($reason) {
                    if (!$reason instanceof Exception) {
                        $reason = new TimeoutException($reason);
                    }
                    $this->cancel($reason);
                });
                
                $onResolved = function () use ($resolve, $timer) {
                    $resolve($this->result);
                    $timer->cancel();
                };
                
                $this->onFulfilled->push($onResolved);
                $this->onRejected->push($onResolved);
            },
            function (Exception $exception) use (&$timer) {
                $timer->cancel();
                
                Loop::schedule(function () use ($exception) {
                    if (0 === --$this->children) {
                        $this->cancel($exception);
                    }
                });
            }
        );
    }
    
    /**
     * @inheritdoc
     */
    public function delay($time)
    {
        if (null !== $this->result) {
            return $this->unwrap()->delay($time);
        }
        
        ++$this->children;
        
        return new static(
            function ($resolve) use (&$timer, $time) {
                $this->onFulfilled->push(function () use (&$timer, $time, $resolve) {
                    $timer = Loop::timer($time, function () use ($resolve) {
                        $resolve($this->result);
                    });
                });
                
                $this->onRejected->push(function () use ($resolve) {
                    $resolve($this->result);
                });
            },
            function (Exception $exception) use (&$timer) {
                if (null !== $timer) {
                    $timer->cancel();
                }
                
                Loop::schedule(function () use ($exception) {
                    if (0 === --$this->children) {
                        $this->cancel($exception);
                    }
                });
            }
        );
    }
    
    /**
     * @inheritdoc
     */
    public function isPending()
    {
        return null === $this->result ?: $this->unwrap()->isPending();
    }
    
    /**
     * @inheritdoc
     */
    public function isFulfilled()
    {
        return null !== $this->result ? $this->unwrap()->isFulfilled() : false;
    }
    
    /**
     * @inheritdoc
     */
    public function isRejected()
    {
        return null !== $this->result ? $this->unwrap()->isRejected() : false;
    }
    
    /**
     * @inheritdoc
     */
    public function getResult()
    {
        if ($this->isPending()) {
            throw new UnresolvedException('The promise is still pending.');
        }
        
        return $this->unwrap()->getResult();
    }
    
    /**
     * @inheritdoc
     */
    public function unwrap()
    {
        if (null !== $this->result) {
            while ($this->result instanceof self && null !== $this->result->result) {
                $this->result = $this->result->result;
            }
            
            return $this->result;
        }
        
        return $this;
    }
    
    /**
     * Return a promise using the given value. There are two possible outcomes depending on the type of the passed value:
     * (1) PromiseInterface: The promise is returned without modification.
     * (2) All other types: A fulfilled promise is returned using the given value as the result.
     *
     * @param   mixed $value
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function resolve($value = null)
    {
        if ($value instanceof PromiseInterface) {
            return $value;
        }
        
        return new FulfilledPromise($value);
    }
    
    /**
     * Create a new rejected promise using the given reason.
     *
     * @param   mixed $reason
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function reject($reason = null)
    {
        return new RejectedPromise($reason);
    }
    
    /**
     * Wraps the given callable $worker in a promise aware function that takes the same number of arguments as $worker, but
     * those arguments may be promises for the future argument value or just values. The returned function will return a
     * promise for the return value of $worker and will never throw. The $worker function will not be called until each
     * promise given as an argument is fulfilled. If any promise provided as an argument rejects, the promise returned by the
     * returned function will be rejected for the same reason. The promise is fulfilled with the return value of $worker or
     * rejected if $worker throws.
     *
     * @param   callable $worker
     *
     * @return  callable
     *
     * @api
     */
    public static function lift(callable $worker)
    {
        $worker = function (array $args) use ($worker) {
            ksort($args); // Needed to ensure correct argument order.
            return call_user_func_array($worker, $args);
        };
        
        /**
         * @param   mixed ...$args Promises or values.
         *
         * @return  PromiseInterface
         */
        return function (/* ...$args */) use ($worker) {
            return static::join(func_get_args())->then($worker);
        };
    }
    
    /**
     * Transforms a function that takes a callback into a function that returns a promise. The promise is fulfilled with an 
     * array of the parameters that would have been passed to the callback function.
     *
     * @param   callable $worker Function that normally accepts a callback.
     * @param   int $index Position of callback in $worker argument list (0-indexed).
     *
     * @return  callable
     *
     * @api
     */
    public static function promisify(callable $worker, $index = 0)
    {
        return function (/* ...$args */) use ($worker, $index) {
            $args = func_get_args();
            
            return new static(function ($resolve) use ($worker, $index, $args) {
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
     * Returns a promise that is resolved when all promises are resolved. The returned promise will not reject by itself
     * (only if cancelled). Returned promise is fulfilled with an array of resolved promises, with keys identical and
     * corresponding to the original given array.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function settle(array $promises)
    {
        if (empty($promises)) {
            return static::resolve([]);
        }
        
        return new static(function ($resolve) use ($promises) {
            $pending = count($promises);
            
            $after = function () use (&$promises, &$pending, $resolve) {
                if (0 === --$pending) {
                    $resolve($promises);
                }
            };
            
            foreach ($promises as &$promise) {
                $promise = static::resolve($promise);
                $promise->after($after);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled when all promises are fulfilled, and rejected if any promise is rejected.
     * Returned promise is fulfilled with an array of values used to fulfill each contained promise, with keys corresponding
     * to the array of promises.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function join(array $promises)
    {
        if (empty($promises)) {
            return static::resolve([]);
        }
        
        return new static(function ($resolve, $reject) use ($promises) {
            $pending = count($promises);
            $values = [];
            
            foreach ($promises as $key => $promise) {
                $onFulfilled = function ($value) use ($key, &$values, &$pending, $resolve) {
                    $values[$key] = $value;
                    if (0 === --$pending) {
                        $resolve($values);
                    }
                };
                
                static::resolve($promise)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled when any promise is fulfilled, and rejected only if all promises are rejected.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function any(array $promises)
    {
        if (empty($promises)) {
            return static::reject(new LogicException('No promises provided.'));
        }
        
        return new static(function ($resolve, $reject) use ($promises) {
            $pending = count($promises);
            $exceptions = [];
            
            foreach ($promises as $key => $promise) {
                $onRejected = function (Exception $exception) use ($key, &$exceptions, &$pending, $reject) {
                    $exceptions[$key] = $exception;
                    if (0 === --$pending) {
                        $reject(new MultiReasonException($exceptions));
                    }
                };
                
                static::resolve($promise)->done($resolve, $onRejected);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled when $required number of promises are fulfilled. The promise is rejected if
     * $required number of promises can no longer be fulfilled.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     * @param   int $required Number of promises that must be fulfilled to fulfill the returned promise.
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function some(array $promises, $required)
    {
        $required = (int) $required;
        
        if (0 >= $required) {
            return static::resolve([]);
        }
        
        if ($required > count($promises)) {
            return static::reject(new LogicException('Too few promises provided.'));
        }
        
        return new static(function ($resolve, $reject) use ($promises, $required) {
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
                
                static::resolve($promise)->done($onFulfilled, $onRejected);
            }
        });
    }
    
    /**
     * Returns a promise that is fulfilled or rejected when the first promise is fulfilled or rejected.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function choose(array $promises)
    {
        if (empty($promises)) {
            return static::reject(new LogicException('No promises provided.'));
        }
        
        return new static(function ($resolve, $reject) use ($promises) {
            foreach ($promises as $promise) {
                static::resolve($promise)->done($resolve, $reject);
            }
        });
    }
    
    /**
     * Maps the callback to each promise as it is fulfilled. Returns an array of promises resolved by the return callback
     * value of the callback function. The callback may return promises or throw exceptions to reject promises in the array.
     * If a promise in the passed array rejects, the callback will not be called and the promise in the array is rejected for
     * the same reason. Tip: Use join() or settle() method to determine when all promises in the array have been resolved.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     * @param   callable $callback (mixed $value) : mixed
     *
     * @return  PromiseInterface[] Array of promises resolved with the result of the mapped function.
     *
     * @api
     */
    public static function map(array $promises, callable $callback)
    {
        $results = [];
        
        foreach ($promises as $key => $promise) {
            $results[$key] = static::resolve($promise)->then($callback);
        }
        
        return $results;
    }
    
    /**
     * Reduce function similar to array_reduce(), only it works on promises and/or values. The callback function may return a
     * promise or value and the initial value may also be a promise or value.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     * @param   callable $callback (mixed $carry, mixed $value) : mixed Called for each fulfilled promise value.
     * @param   mixed $initial The initial value supplied for the $carry parameter of the callback function.
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function reduce(array $promises, callable $callback, $initial = null)
    {
        if (empty($promises)) {
            return static::resolve($initial);
        }
        
        return new static(function ($resolve, $reject) use ($promises, $callback, $initial) {
            $pending = count($promises);
            $carry = static::resolve($initial);
            $carry->done(null, $reject);
            
            $onFulfilled = function ($value) use (&$carry, &$pending, $callback, $resolve, $reject) {
                $carry = $carry->then(function ($carry) use ($callback, $value) {
                    return $callback($carry, $value);
                });
                $carry->done(null, $reject);
                
                if (0 === --$pending) {
                    $resolve($carry);
                }
            };
            
            foreach ($promises as $promise) {
                static::resolve($promise)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Calls $worker using the return value of the previous call until $predicate returns true. $seed is used as the initial
     * parameter to $worker. $predicate is called before $worker with the value to be passed to $worker. If $worker or
     * $predicate throws an exception, the promise is rejected using that exception. The call stack is cleared before each
     * call to $worker to avoid filling the call stack. If $worker returns a promise, iteration waits for the returned
     * promise to be resolved.
     *
     * @param   callable $worker (mixed $value) : mixed Called with the previous return value on each iteration.
     * @param   callable $predicate (mixed $value) : bool Return true to stop iteration and fulfill promise.
     * @param   mixed $seed Initial value given to $predicate and $worker (may be a promise).
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function iterate(callable $worker, callable $predicate, $seed = null)
    {
        return new static(function ($resolve, $reject) use ($worker, $predicate, $seed) {
            $callback = function ($value) use (&$callback, $worker, $predicate, $resolve, $reject) {
                try {
                    if ($predicate($value)) { // Resolve promise if predicate returns true.
                        $resolve($value);
                    } else {
                        static::resolve($worker($value))->done($callback, $reject);
                    }
                } catch (Exception $exception) {
                    $reject($exception);
                }
            };
            
            static::resolve($seed)->done($callback, $reject); // Start iteration with $seed.
        });
    }
}
