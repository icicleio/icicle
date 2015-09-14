<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise;

use Icicle\Promise\Exception\UnexpectedTypeError;

trait PromiseTrait
{
    /**
     * @param callable $onFulfilled
     * @param callable $onRejected
     *
     * @return \Icicle\Promise\PromiseInterface
     */
    abstract public function then(callable $onFulfilled = null, callable $onRejected = null): PromiseInterface;
    
    /**
     * {@inheritdoc}
     */
    public function capture(callable $onRejected): PromiseInterface
    {
        return $this->then(null, function (\Throwable $exception) use ($onRejected) {
            if ($onRejected instanceof \Closure) { // Closure.
                $reflection = new \ReflectionFunction($onRejected);
            } elseif (is_array($onRejected)) { // Methods passed as an array.
                $reflection = new \ReflectionMethod($onRejected[0], $onRejected[1]);
            } elseif (is_object($onRejected)) { // Callable objects.
                $reflection = new \ReflectionMethod($onRejected, '__invoke');
            } elseif (is_string($onRejected) && strrpos($onRejected, '::', -1)) { // ClassName::methodName strings.
                $reflection = new \ReflectionMethod($onRejected);
            } else { // Everything else.
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
    public function tap(callable $onFulfilled): PromiseInterface
    {
        return $this->then(function ($value) use ($onFulfilled) {
            $result = $onFulfilled($value);
            if ($result instanceof PromiseInterface) {
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
    public function cleanup(callable $onResolved): PromiseInterface
    {
        $onResolved = function () use ($onResolved) {
            $result = $onResolved();
            if ($result instanceof PromiseInterface) {
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
    public function splat(callable $onFulfilled): PromiseInterface
    {
        return $this->then(function ($values) use ($onFulfilled) {
            if ($values instanceof \Traversable) {
                $values = iterator_to_array($values);
            } elseif (!is_array($values)) {
                throw new UnexpectedTypeError(sprintf(
                    'Expected array or Traversable for promise result, got %s',
                    is_object($values) ? get_class($values) : gettype($values)
                ));
            }

            ksort($values); // Ensures correct argument order.
            return $onFulfilled(...$values);
        });
    }
}
