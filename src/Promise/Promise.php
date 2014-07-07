<?php
namespace Icicle\Promise;

use Exception;
use Icicle\Promise\Exception\CancelledException;
use Icicle\Promise\Exception\LogicException;
use Icicle\Promise\Exception\MultiReasonException;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Promise\Exception\TypeException;
use Icicle\Promise\Exception\UnresolvedException;
use Icicle\Timer\Timer;

class Promise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var     PromiseInterface|null
     */
    private $result;
    
    /**
     * @var     ThenQueue
     */
    private $onFulfilled;
    
    /**
     * @var     ThenQueue
     */
    private $onRejected;
    
    /**
     * @var     Closure
     */
    private $onCancelled;
    
    /**
     * @var     Promise|null
     */
    private $source;
    
    /**
     * @param   callable $worker
     * @param   callable|null $onCancelled
     */
    public function __construct(callable $worker, callable $onCancelled = null)
    {
        $this->onFulfilled = new ThenQueue();
        $this->onRejected = new ThenQueue();
        
        /**
         * Fulfills the promise with the given value. Calls self::resolve() on given value to generate
         * fulfilled a promise.
         *
         * @param   mixed $value A promise can be fulfilled with anything other than itself.
         *
         * @throws  TypeException Thrown if self is used to fulfill the promise.
         */
        $resolve = function ($value = null) {
            if (null === $this->result) {
                if ($value instanceof self) {
                    $result = $value;
                    do {
                        if ($this === $result) {
                            throw new TypeException('Circular reference detected in promise fulfillment chain (fulfilling with self).');
                        }
                        $result = $result->result;
                    } while ($result instanceof self);
                }
                
                $this->source = null;
                $this->result = static::resolve($value);
                if (!$this->onFulfilled->isEmpty()) {
                    $this->result->done($this->onFulfilled);
                }
            }
        };
        
        /**
         * Rejects the promise with the given exception. Calls self::reject() on exception to generate
         * a rejected promise.
         *
         * @param   Exception $exception
         */
        $reject = function (Exception $exception) {
            if (null === $this->result) {
                $this->source = null;
                $this->result = static::reject($exception);
                if (!$this->onRejected->isEmpty()) {
                    $this->result->done(null, $this->onRejected);
                }
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
            $worker($resolve, $reject);
        } catch (Exception $exception) {
            $reject($exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            return $this->result->then($onFulfilled, $onRejected);
        }
        
        if (null === $onFulfilled && null === $onRejected) {
            return $this;
        }
        
        $promise = new static(function ($resolve, $reject) use ($onFulfilled, $onRejected) {
            if (null !== $onFulfilled) {
                $this->onFulfilled->insert(function ($value) use ($resolve, $reject, $onFulfilled) {
                    try {
                        $resolve($onFulfilled($value));
                    } catch (Exception $exception) {
                        $reject($exception);
                    }
                });
            } else {
                $this->onFulfilled->insert($resolve);
            }
            
            if (null !== $onRejected) {
                $this->onRejected->insert(function (Exception $exception) use ($resolve, $reject, $onRejected) {
                    try {
                        $resolve($onRejected($exception));
                    } catch (Exception $exception) {
                        $reject($exception);
                    }
                });
            } else {
                $this->onRejected->insert($reject);
            }
        });
        
        $promise->source = $this;
        
        return $promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            $this->result->done($onFulfilled, $onRejected);
        } else {
            if (null !== $onFulfilled) {
                $this->onFulfilled->insert($onFulfilled);
            }
            
            if (null !== $onRejected) {
                $this->onRejected->insert($onRejected);
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $exception = null)
    {
        if (null !== $this->result) {
            $this->result->cancel($exception);
        } elseif (null === $this->source) {
            if (null === $exception) {
                $exception = new CancelledException('The promise was cancelled.');
            }
            
            $onCancelled = $this->onCancelled;
            $onCancelled($exception);
        } else {
            // Find the most distant ancestor and cancel that promise.
            $source = $this->source;
            while (null !== $source->source) {
                $source = $source->source;
            }
            
            $source->cancel($exception);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, Exception $exception = null)
    {
        if (null !== $this->result) {
            return $this->result->timeout($timeout, $exception);
        }
        
        if (null === $exception) {
            $exception = new TimeoutException('The promise timed out.');
        }
        
        $promise = new static(function ($resolve, $reject) use ($timeout, $exception) {
            $timer = Timer::once(function () use ($reject, $exception) {
                $reject($exception);
            }, $timeout);
            
            $this->after(function () use ($timer) { $timer->cancel(); });
            $this->done($resolve, $reject);
        });
        
        $promise->source = $this;
        
        return $promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        if (null !== $this->result) {
            return $this->result->delay($time);
        }
        
        $promise = new static(
            function ($resolve, $reject) use (&$timer, $time) {
                $this->done(function ($value) use (&$timer, $time, $resolve) {
                    $timer = Timer::once(function () use ($resolve, $value) {
                        $resolve($value);
                    });
                }, $reject);
            },
            function (Exception $exception) use (&$timer) {
                if (null !== $timer) {
                    $timer->cancel();
                }
            }
        );
        
        $promise->source = $this;
        
        return $promise;
    }
    
    /**
     * {@inheritdoc}
     */
    public function fork(callable $onCancelled = null)
    {
        if (null !== $this->result) {
            return $this->result->fork($onCancelled);
        }
        
        return new static(function ($resolve, $reject) {
            $this->done($resolve, $reject);
        }, $onCancelled);
    }
    
    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        return new UncancellablePromise($this);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return null === $this->result ?: $this->result->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return null !== $this->result ? $this->result->isFulfilled() : false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return null !== $this->result ? $this->result->isRejected() : false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        if ($this->isPending()) {
            throw new UnresolvedException('The promise is still pending.');
        }
        
        return $this->result->getResult();
    }
    
    /**
     * Return a promise using the given value. There are three possible outcomes depending on the type of the passed value:
     * (1) PromiseInterface: The promise is returned without modification.
     * (2) PromisorInterface: The contained promise is returned by calling PromisorInterface::getPromise().
     * (3) All other types: A fulfilled (resolved) promise (FulfilledPromise) is returned.
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
        
        if ($value instanceof PromisorInterface) {
            return $value->getPromise();
        }
        
        return new FulfilledPromise($value);
    }
    
    /**
     * Create a new rejected promise (RejectedPromise) using the given exception as the rejection reason.
     *
     * @param   Exception $exception
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function reject(Exception $exception)
    {
        return new RejectedPromise($exception);
    }
    
    /**
     * Wraps the given callable $worker in a promise aware function that takes the same number of arguments as $worker, but
     * those arguments may be promises for the future argument value or just values. The returned function will return a
     * promise for the return value of $worker and will never throw. The $worker function will not be called until each promise
     * given as an argument is fulfilled. If any promise provided as an argument rejects, the promise returned by the
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
        /**
         * @param   mixed ...$args Promises or values.
         *
         * @return  PromiseInterface
         */
        return function (/* ...$args */) use ($worker) {
            return static::fold(func_get_args(), $worker);
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
    public static function promisify(callable $worker, $index = 1)
    {
        return function (/* ...$args */) use ($index) {
            $args = func_get_args();
            
            return new static(function ($resolve) use ($index, $args) {
                $callback = function (/* ...$args */) use ($resolve) {
                    $resolve(func_get_args());
                };
                
                array_splice($args, $index, 0, [$callback]);
                
                call_user_func_array($worker, $args);
            });
        };
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
     * Calls the given callback function using the resolution values of the given promises or values as the parameters
     * to the function. Parameters are passed to the function in the iteration order of the passed array. The returned
     * promise is fulfilled using the return value of $callback or rejected if $callback throws or if any of the given
     * promises are rejected.
     *
     * @param   mixed[] $promises
     * @param   callable $callback
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function fold(array $promises, callable $callback)
    {
        return static::join($promises)->then(function (array $values) use ($callback) {
            return call_user_func_array($callback, $values);
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
            return static::resolve(null);
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
     * Maps the callback to each promise as it is fulfilled. Returns a promise that is fulfilled with an array of values only
     * if all promises are fulfilled and the callback never throws an exception. Callback may return a promise whose resolution
     * value will determine the resolution value of the promise returned by this function.
     *
     * @param   mixed[] $promises Promises or values (passed through resolve() to create promises).
     * @param   callable $callback (mixed $value) : mixed
     *
     * @return  PromiseInterface
     *
     * @api
     */
    public static function map(array $promises, callable $callback)
    {
        if (empty($promises)) {
            return static::resolve([]);
        }
        
        return new static(function ($resolve, $reject) use ($promises, $callback) {
            $pending = count($promises);
            $values = [];
            
            foreach ($promises as $key => $promise) {
                $onFulfilled = function ($value) use ($key, &$values, &$pending, $resolve) {
                    $values[$key] = $value;
                    if (0 === --$pending) {
                        $resolve($values);
                    }
                };
                
                static::resolve($promise)->then($callback)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Reduce function similar to array_reduce(), only it works on promises and/or values. The callback function may return a promise
     * or value and the initial value may also be a promise or value.
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
            $callback = static::lift($callback);
            $carry = static::resolve($initial);
            
            foreach ($promises as $promise) {
                $onFulfilled = function ($value) use (&$carry, &$pending, $callback, $resolve, $reject) {
                    $carry = $callback($carry, $value);
                    $carry->otherwise($reject);
                    if (0 === --$pending) {
                        $resolve($carry);
                    }
                };
                
                static::resolve($promise)->done($onFulfilled, $reject);
            }
        });
    }
    
    /**
     * Calls $worker using the return value of the previous call until $predicate returns true. $seed is used as the initial
     * parameter to $worker. $predicate is called before $worker with the value to be passed to $worker. If $worker or $predicate
     * throws an exception, the promise is rejected using that exception. The call stack is cleared before each call to $worker
     * to avoid filling the call stack. If $worker returns a promise, iteration waits for the returned promise to be resolved.
     *
     * @param   callable $worker (mixed $value) : mixed Called with the previous return value on each interation.
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
                    } else { // Otherwise use result of $worker in promise context (so promises returned by $worker delay iteration).
                        self::resolve($worker($value))->done($callback, $reject);
                    }
                } catch (Exception $exception) {
                    $reject($exception);
                }
            };
            
            static::resolve($seed)->done($callback, $reject); // Start iteration with $seed.
        });
    }
}
