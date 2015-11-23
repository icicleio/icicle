<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

use Exception;
use Icicle\Awaitable\Exception\CircularResolutionError;
use Icicle\Awaitable\Exception\TimeoutException;
use Icicle\Awaitable\Exception\UnresolvedError;
use Icicle\Awaitable\Internal\DoneQueue;
use Icicle\Loop;

/**
 * Awaitable implementation based on the Promises/A+ specification adding support for cancellation. This class should
 * be extended to create awaitable implementations. There is no way to externally resolve a Future, so the extending
 * class must either use or expose the resolve() and reject() methods.
 *
 * @see http://promisesaplus.com
 */
class Future implements Awaitable
{
    use Internal\AwaitableMethods;

    /**
     * @var \Icicle\Awaitable\Awaitable|null
     */
    private $result;
    
    /**
     * @var callable|\Icicle\Awaitable\Internal\DoneQueue|null
     */
    private $onFulfilled;
    
    /**
     * @var callable|\Icicle\Awaitable\Internal\DoneQueue|null
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
     * @param callable|null $onCancelled
     */
    public function __construct(callable $onCancelled = null)
    {
        $this->onCancelled = $onCancelled;
    }

    /**
     * Resolves the awaitable with the given awaitable or value. If another awaitable, this awaitable takes on the state
     * of that awaitable. If a value, the awaitable will be fulfilled with that value.
     *
     * @param mixed $value An awaitable can be resolved with anything other than itself.
     */
    protected function resolve($value = null)
    {
        if (null !== $this->result) {
            return;
        }

        if ($value instanceof self) {
            $value = $value->unwrap();
            if ($this === $value) {
                $value = new Internal\RejectedAwaitable(
                    new CircularResolutionError('Circular reference in awaitable resolution chain.')
                );
            }
        } elseif (!$value instanceof Awaitable) {
            $value = new Internal\FulfilledAwaitable($value);
        }

        $this->result = $value;
        $this->result->done($this->onFulfilled, $this->onRejected ?: new DoneQueue());

        $this->onFulfilled = null;
        $this->onRejected = null;
        $this->onCancelled = null;
    }

    /**
     * Rejects the awaitable with the given exception.
     *
     * @param \Exception $reason
     */
    protected function reject(Exception $reason)
    {
        $this->resolve(new Internal\RejectedAwaitable($reason));
    }

    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $this->result) {
            return $this->unwrap()->then($onFulfilled, $onRejected);
        }

        if (null === $onFulfilled && null === $onRejected) {
            return $this;
        }

        ++$this->children;

        $future = new self(function (Exception $exception) {
            if (0 === --$this->children) {
                $this->cancel($exception);
            }
        });

        if (null !== $onFulfilled) {
            $onFulfilled = function ($value) use ($future, $onFulfilled) {
                try {
                    $future->resolve($onFulfilled($value));
                } catch (Exception $exception) {
                    $future->reject($exception);
                }
            };
        } else {
            $onFulfilled = function () use ($future) {
                $future->resolve($this->result);
            };
        }

        if (null !== $onRejected) {
            $onRejected = function (Exception $exception) use ($future, $onRejected) {
                try {
                    $future->resolve($onRejected($exception));
                } catch (Exception $exception) {
                    $future->reject($exception);
                }
            };
        } else {
            $onRejected = function () use ($future) {
                $future->resolve($this->result);
            };
        }

        $this->done($onFulfilled, $onRejected);

        return $future;
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

        if (null === $onRejected) {
            $onRejected = function (Exception $exception) {
                throw $exception;
            };
        }

        if (null !== $onFulfilled) {
            if (null === $this->onFulfilled) {
                $this->onFulfilled = $onFulfilled;
            } elseif (!$this->onFulfilled instanceof DoneQueue) {
                $this->onFulfilled = new DoneQueue($this->onFulfilled);
                $this->onFulfilled->push($onFulfilled);
            } else {
                $this->onFulfilled->push($onFulfilled);
            }
        }

        if (null === $this->onRejected) {
            $this->onRejected = $onRejected;
        } elseif (!$this->onRejected instanceof DoneQueue) {
            $this->onRejected = new DoneQueue($this->onRejected);
            $this->onRejected->push($onRejected);
        } else {
            $this->onRejected->push($onRejected);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $reason = null)
    {
        if (null !== $this->result) {
            $this->unwrap()->cancel($reason);
            return;
        }

        $this->resolve(new Internal\CancelledAwaitable($reason, $this->onCancelled));
    }

    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, callable $onTimeout = null)
    {
        if (null !== $this->result) {
            return $this->unwrap()->timeout($timeout, $onTimeout);
        }
        
        ++$this->children;

        $future = new self(function (Exception $exception) {
            if (0 === --$this->children) {
                $this->cancel($exception);
            }
        });

        $timer = Loop\timer($timeout, function () use ($future, $onTimeout) {
            if (null === $onTimeout) {
                $future->reject(new TimeoutException());
                return;
            }

            try {
                $future->resolve($onTimeout());
            } catch (Exception $exception) {
                $future->reject($exception);
            }
        });

        $onResolved = function () use ($future, $timer) {
            $future->resolve($this->result);
            $timer->stop();
        };

        $this->done($onResolved, $onResolved);

        return $future;
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

        $future = new self(function (Exception $exception) use (&$timer) {
            if (null !== $timer) {
                $timer->stop();
            }

            if (0 === --$this->children) {
                $this->cancel($exception);
            }
        });

        $onFulfilled = function () use (&$timer, $time, $future) {
            $timer = Loop\timer($time, function () use ($future) {
                $future->resolve($this->result);
            });
        };

        $onRejected = function () use ($future) {
            $future->resolve($this->result);
        };

        $this->done($onFulfilled, $onRejected);

        return $future;
    }

    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        if (null !== $this->result) {
            return $this->unwrap()->uncancellable();
        }

        return new Internal\UncancellableAwaitable($this);
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        while (null === $this->result) {
            if (Loop\isEmpty()) {
                throw new UnresolvedError('Loop emptied without resolving the awaitable.');
            }

            Loop\tick(true);
        }

        return $this->unwrap()->wait();
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
    public function isCancelled()
    {
        return null !== $this->result ? $this->unwrap()->isCancelled() : false;
    }

    /**
     * @return \Icicle\Awaitable\Awaitable
     */
    private function unwrap()
    {
        while ($this->result instanceof self && null !== $this->result->result) {
            $this->result = $this->result->result;
        }

        return $this->result ?: $this;
    }
}
