<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable;

use Icicle\Awaitable\Exception\InvalidResolverError;

/**
 * Promise implementation based on the Promises/A+ specification adding support for cancellation.
 *
 * @see http://promisesaplus.com
 */
final class Promise extends Future
{
    /**
     * @param callable<(callable $resolve, callable $reject, Loop $loop): callable|null> $resolver
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
         * @param mixed $reason
         */
        $reject = function ($reason = null) {
            $this->reject($reason);
        };

        try {
            $onCancelled = $resolver($resolve, $reject);
            if (null !== $onCancelled && !is_callable($onCancelled)) {
                throw new InvalidResolverError('The resolver must return a callable or null.');
            }
            parent::__construct($onCancelled);
        } catch (\Exception $exception) {
            parent::__construct();
            $reject($exception);
        }
    }
}
