<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Promise\Exception\CancelledException;
use Icicle\Promise\Exception\CircularResolutionError;
use Icicle\Promise\Exception\InvalidResolverError;
use Icicle\Promise\Exception\TimeoutException;
use Icicle\Promise\Exception\UnresolvedError;
use Icicle\Promise\Structures\FulfilledPromise;
use Icicle\Promise\Structures\RejectedPromise;
use Icicle\Promise\Structures\ThenQueue;

/**
 * Promise implementation based on the Promises/A+ specification adding support for cancellation.
 *
 * @see http://promisesaplus.com
 */
class Promise implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var \Icicle\Promise\PromiseInterface|null
     */
    private $result;
    
    /**
     * @var callable|\Icicle\Promise\Structures\ThenQueue|null
     */
    private $onFulfilled;
    
    /**
     * @var callable|\Icicle\Promise\Structures\ThenQueue|null
     */
    private $onRejected;
    
    /**
     * @var callable|null
     */
    private $onCancelled;
    
    /**
     * @var int
     */
    private $children = 0;
    
    /**
     * @param callable $resolver
     * @param callable|null $onCancelled
     */
    public function __construct(callable $resolver, callable $onCancelled = null)
    {
        /**
         * Resolves the promise with the given promise or value. If another promise, this promise takes
         * on the state of that promise. If a value, the promise will be fulfilled with that value.
         *
         * @param mixed $value A promise can be resolved with anything other than itself.
         */
        $resolve = function ($value = null) {
            if ($value instanceof PromiseInterface) {
                $value = $value->unwrap();
                if ($this === $value) {
                    $value = new RejectedPromise(
                        new CircularResolutionError('Circular reference in promise resolution chain.')
                    );
                }
            } else {
                $value = new FulfilledPromise($value);
            }

            $this->resolve($value);
        };
        
        /**
         * Rejects the promise with the given exception.
         *
         * @param mixed $reason
         */
        $reject = function ($reason = null) {
            $this->resolve(new RejectedPromise($reason));
        };

        $this->onCancelled = $onCancelled;

        try {
            $this->onCancelled = $resolver($resolve, $reject);
            if (null !== $this->onCancelled && !is_callable($this->onCancelled)) {
                throw new InvalidResolverError('The resolver must return a callable or null.');
            }
        } catch (Exception $exception) {
            $reject($exception);
        }
    }

    /**
     * Resolves this promise with the given promise if this promise is still pending.
     *
     * @param \Icicle\Promise\PromiseInterface $result
     */
    private function resolve(PromiseInterface $result)
    {
        if (null !== $this->result) {
            return;
        }

        $this->result = $result;
        $this->result->done($this->onFulfilled, $this->onRejected ?: new ThenQueue());

        $this->onFulfilled = null;
        $this->onRejected = null;
        $this->onCancelled = null;
    }

    /**
     * Adds callback to the onFulfilled queue.
     *
     * @param callable $onFulfilled
     */
    private function onFulfilled(callable $onFulfilled)
    {
        if (null === $this->onFulfilled) {
            $this->onFulfilled = $onFulfilled;
            return;
        }

        if (!$this->onFulfilled instanceof ThenQueue) {
            $this->onFulfilled = new ThenQueue($this->onFulfilled);
        }

        $this->onFulfilled->push($onFulfilled);
    }

    /**
     * Adds callback to the onRejected queue.
     *
     * @param callable $onRejected
     */
    private function onRejected(callable $onRejected)
    {
        if (null === $this->onRejected) {
            $this->onRejected = $onRejected;
            return;
        }

        if (!$this->onRejected instanceof ThenQueue) {
            $this->onRejected = new ThenQueue($this->onRejected);
        }

        $this->onRejected->push($onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            return $this->unwrap()->then($onFulfilled, $onRejected);
        }
        
        ++$this->children;
        
        return new self(
            function (callable $resolve, callable $reject) use ($onFulfilled, $onRejected) {
                if (null !== $onFulfilled) {
                    $this->onFulfilled(function ($value) use ($resolve, $reject, $onFulfilled) {
                        try {
                            $resolve($onFulfilled($value));
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    });
                } else {
                    $this->onFulfilled(function () use ($resolve) {
                        $resolve($this->result);
                    });
                }
                
                if (null !== $onRejected) {
                    $this->onRejected(function (Exception $exception) use ($resolve, $reject, $onRejected) {
                        try {
                            $resolve($onRejected($exception));
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    });
                } else {
                    $this->onRejected(function () use ($resolve) {
                        $resolve($this->result);
                    });
                }

                return function (Exception $exception) {
                    Loop\queue(function () use ($exception) {
                        if (0 === --$this->children) {
                            $this->cancel($exception);
                        }
                    });
                };
            }
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            $this->unwrap()->done($onFulfilled, $onRejected);
            return;
        }

        if (null !== $onFulfilled) {
            $this->onFulfilled($onFulfilled);
        }

        $this->onRejected($onRejected ?: function (Exception $exception) {
            throw $exception; // Rethrow exception in uncatchable way.
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel($reason = null)
    {
        if (null !== $this->result) {
            $this->unwrap()->cancel($reason);
            return;
        }

        if (!$reason instanceof Exception) {
            $reason = new CancelledException($reason);
        }

        if (null !== $this->onCancelled) {
            try {
                $onCancelled = $this->onCancelled;
                $onCancelled($reason);
            } catch (Exception $exception) {
                $reason = $exception; // Thrown exception will now be used to reject promise.
            }
        }

        $this->resolve(new RejectedPromise($reason));
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, $reason = null)
    {
        if (null !== $this->result) {
            return $this->unwrap()->timeout($timeout, $reason);
        }
        
        ++$this->children;
        
        return new self(
            function (callable $resolve) use ($timeout, $reason) {
                $timer = Loop\timer($timeout, function () use ($reason) {
                    if (!$reason instanceof Exception) {
                        $reason = new TimeoutException($reason);
                    }
                    $this->cancel($reason);
                });
                
                $onResolved = function () use ($resolve, $timer) {
                    $resolve($this->result);
                    $timer->stop();
                };
                
                $this->onFulfilled($onResolved);
                $this->onRejected($onResolved);

                return function (Exception $exception) use ($timer) {
                    $timer->stop();

                    Loop\queue(function () use ($exception) {
                        if (0 === --$this->children) {
                            $this->cancel($exception);
                        }
                    });
                };
            }
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        if (null !== $this->result) {
            return $this->unwrap()->delay($time);
        }
        
        ++$this->children;
        
        return new self(
            function (callable $resolve) use ($time) {
                $this->onFulfilled(function () use (&$timer, $time, $resolve) {
                    $timer = Loop\timer($time, function () use ($resolve) {
                        $resolve($this->result);
                    });
                });
                
                $this->onRejected(function () use ($resolve) {
                    $resolve($this->result);
                });

                return function (Exception $exception) use (&$timer) {
                    if (null !== $timer) {
                        $timer->stop();
                    }

                    Loop\queue(function () use ($exception) {
                        if (0 === --$this->children) {
                            $this->cancel($exception);
                        }
                    });
                };
            }
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return null === $this->result ?: $this->unwrap()->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return null !== $this->result ? $this->unwrap()->isFulfilled() : false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return null !== $this->result ? $this->unwrap()->isRejected() : false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        if (null === $this->result) {
            throw new UnresolvedError('The promise is still pending.');
        }
        
        return $this->unwrap()->getResult();
    }
    
    /**
     * {@inheritdoc}
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
}
