<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Exception;

class FreedError extends \Exception implements Error
{
    public function __construct()
    {
        parent::__construct('The socket event object has been freed and can no longer be used.');
    }
}
