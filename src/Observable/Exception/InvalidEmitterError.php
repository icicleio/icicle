<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Observable\Exception;

use Icicle\Exception\InvalidArgumentError;

class InvalidEmitterError extends InvalidArgumentError implements Error
{
    /**
     * @var callable
     */
    private $callable;

    /**
     * @param string $message
     * @param callable $callable
     * @param \Exception|null $previous
     */
    public function __construct(callable $callable, \Exception $previous = null)
    {
        parent::__construct('Invalid observable emitter.', 0, $previous);

        $this->callable = $callable;
    }

    /**
     * @return callable
     */
    public function getCallable()
    {
        return $this->callable;
    }
}
