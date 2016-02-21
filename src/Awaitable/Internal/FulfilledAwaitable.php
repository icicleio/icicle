<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Exception;
use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Promise;
use Icicle\Exception\InvalidArgumentError;
use Icicle\Loop;

class FulfilledAwaitable extends ResolvedAwaitable
{
    /**
     * @var mixed
     */
    private $value;
    
    /**
     * @param mixed $value Anything other than an Awaitable object.
     *
     * @throws \Icicle\Exception\InvalidArgumentError If an awaitable is given as the value.
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

        try {
            $result = $onFulfilled($this->value);
        } catch (Exception $exception) {
            return new RejectedAwaitable($exception);
        }

        if (!$result instanceof Awaitable) {
            $result = new self($result);
        }

        return $result;
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onFulfilled) {
            try {
                $onFulfilled($this->value);
            } catch (Exception $exception) {
                Loop\queue(function () use ($exception) {
                    throw $exception; // Rethrow exception in uncatchable way.
                });
            }
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
