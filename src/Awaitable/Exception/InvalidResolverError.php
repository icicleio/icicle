<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Exception;

use Icicle\Exception\InvalidArgumentError;

class InvalidResolverError extends InvalidArgumentError implements Error
{
    /**
     * @var callable
     */
    private $resolver;

    /**
     * @param callable $resolver
     */
    public function __construct(callable $resolver)
    {
        parent::__construct('The resolver must return a callable or null.');

        $this->resolver = $resolver;
    }

    /**
     * @return callable
     */
    public function getResolver()
    {
        return $this->resolver;
    }
}
