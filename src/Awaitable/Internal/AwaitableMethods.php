<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Internal;

use Icicle\Awaitable\Awaitable;
use Icicle\Exception\UnexpectedTypeError;

trait AwaitableMethods
{
    /**
     * @param callable $onFulfilled
     * @param callable $onRejected
     *
     * @return \Icicle\Awaitable\Awaitable
     */
    abstract public function then(callable $onFulfilled = null, callable $onRejected = null);
    
    /**
     * {@inheritdoc}
     */
    public function capture(callable $onRejected)
    {
        return $this->then(null, function (\Exception $exception) use ($onRejected) {
            if ($onRejected instanceof \Closure) { // Closure.
                $reflection = new \ReflectionFunction($onRejected);
            } elseif (is_array($onRejected)) { // Methods passed as an array.
                $reflection = new \ReflectionMethod($onRejected[0], $onRejected[1]);
            } elseif (is_object($onRejected)) { // Callable objects.
                $reflection = new \ReflectionMethod($onRejected, '__invoke');
            } else { // Everything else (method names delimited by :: do not work with $callable() syntax before PHP 7).
                $reflection = new \ReflectionFunction($onRejected);
            }
            
            $parameters = $reflection->getParameters();
            
            if (empty($parameters)) { // No parameters defined.
                return $onRejected($exception); // Providing argument in case func_get_args() is used in function.
            }
            
            $class = $parameters[0]->getClass();
            
            if (null === $class || $class->isInstance($exception)) { // None or matching type declaration.
                return $onRejected($exception);
            }
            
            return $this; // Type declaration does not match.
        });
    }

    /**
     * {@inheritdoc}
     */
    public function tap(callable $onFulfilled)
    {
        return $this->then(function ($value) use ($onFulfilled) {
            $result = $onFulfilled($value);
            if ($result instanceof Awaitable) {
                return $result->then(function () {
                    return $this;
                });
            }
            return $this;
        });
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup(callable $onResolved)
    {
        $onResolved = function () use ($onResolved) {
            $result = $onResolved();
            if ($result instanceof Awaitable) {
                return $result->then(function () {
                    return $this;
                });
            }
            return $this;
        };

        return $this->then($onResolved, $onResolved);
    }

    /**
     * {@inheritdoc}
     */
    public function splat(callable $onFulfilled)
    {
        return $this->then(function ($values) use ($onFulfilled) {
            if ($values instanceof \Traversable) {
                $values = iterator_to_array($values);
            } elseif (!is_array($values)) {
                throw new UnexpectedTypeError('array or Traversable', $values);
            }

            ksort($values); // Ensures correct argument order.
            return call_user_func_array($onFulfilled, $values);
        });
    }
}
