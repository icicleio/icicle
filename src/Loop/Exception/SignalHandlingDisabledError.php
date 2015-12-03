<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Exception;

class SignalHandlingDisabledError extends \Exception implements Error
{
    /**
     * @param string|null $message
     */
    public function __construct($message = null)
    {
        parent::__construct($message ?: 'Signal handling is not enabled.');
    }
}
