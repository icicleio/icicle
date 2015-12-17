<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

use Icicle\Awaitable\Exception\InvalidResolverError;

/**
 * A Promise is an awaitable that provides the functions to resolve or reject the promise to the resolver function
 * given to the constructor. A Promise cannot be externally resolved. Only the functions provided to the constructor
 * may resolve the Promise. The cancellation function should be returned from the resolver function (or null if no
 * cancellation function is needed).
 */
final class Promise extends Future
{
    /**
     * @param callable(callable $resolve, callable $reject): callable|null $resolver
     */
    public function __construct(callable $resolver)
    {
        /**
         * Resolves the promise with the given promise or value. If another promise, this promise takes
         * on the state of that promise. If a value, the promise will be fulfilled with that value.
         *
         * @param mixed $value A promise can be resolved with anything other than itself.
         */
        $resolve = function ($value = null) {
            $this->resolve($value);
        };
        
        /**
         * Rejects the promise with the given exception.
         *
         * @param \Exception $reason
         */
        $reject = function (\Exception $reason) {
            $this->reject($reason);
        };

        try {
            $onCancelled = $resolver($resolve, $reject);
            if (null !== $onCancelled && !is_callable($onCancelled)) {
                throw new InvalidResolverError($resolver);
            }
            parent::__construct($onCancelled);
        } catch (\Exception $exception) {
            parent::__construct();
            $this->reject($exception);
        }
    }
}
