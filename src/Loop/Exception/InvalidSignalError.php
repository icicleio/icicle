<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Exception;

use Icicle\Exception\InvalidArgumentError;

class InvalidSignalError extends InvalidArgumentError implements Error
{
    /**
     * @param int $signo
     */
    public function __construct($signo)
    {
        parent::__construct(sprintf('Invalid signal number: %d.', $signo));
    }
}
