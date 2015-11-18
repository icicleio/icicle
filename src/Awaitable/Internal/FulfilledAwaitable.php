<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Exception;
use Icicle\Loop;
use Icicle\Awaitable\Exception\InvalidArgumentError;
use Icicle\Awaitable\Promise;
use Icicle\Awaitable\Awaitable;

class FulfilledAwaitable extends ResolvedAwaitable
{
    /**
     * @var mixed
     */
    private $value;
    
    /**
     * @param mixed $value Anything other than an Awaitable object.
     *
     * @throws \Icicle\Awaitable\Exception\InvalidArgumentError If an awaitable is given as the value.
     */
    public function __construct($value)
    {
        if ($value instanceof Awaitable) {
            throw new InvalidArgumentError('Cannot use an awaitable as a fulfillment value.');
        }
        
        $this->value = $value;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null === $onFulfilled) {
            return $this;
        }
        
        return new Promise(function (callable $resolve, callable $reject) use ($onFulfilled) {
            Loop\queue(function () use ($resolve, $reject, $onFulfilled) {
                try {
                    $resolve($onFulfilled($this->value));
                } catch (Exception $exception) {
                    $reject($exception);
                }
            });
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onFulfilled) {
            Loop\queue($onFulfilled, $this->value);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return new Promise(
            function (callable $resolve) use ($time) {
                $timer = Loop\timer($time, function () use ($resolve) {
                    $resolve($this);
                });

                return function () use ($timer) {
                    $timer->stop();
                };
            }
        );
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function wait()
    {
        return $this->value;
    }
}
