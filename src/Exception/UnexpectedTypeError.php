<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Exception;

class UnexpectedTypeError extends InvalidArgumentError implements Error
{
    /**
     * @param string $expected
     * @param string $type
     */
    public function __construct($expected, $type)
    {
        parent::__construct(sprintf(
            'Expected %s for argument type, instead got %s',
            $expected,
            is_object($type) ? get_class($type) : gettype($type)
        ));
    }
}
