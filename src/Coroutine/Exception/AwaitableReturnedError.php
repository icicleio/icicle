<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine\Exception;

use Icicle\Awaitable\Awaitable;
use Icicle\Exception\InvalidArgumentError;

class AwaitableReturnedError extends InvalidArgumentError implements Error
{
    /**
     * @var \Icicle\Awaitable\Awaitable
     */
    private $awaitable;
    
    /**
     * @param \Icicle\Awaitable\Awaitable $awaitable
     */
    public function __construct(Awaitable $awaitable)
    {
        parent::__construct('Awaitable returned from Coroutine. Use "return yield $awaitable;" to return the fulfillment value of $awaitable.');
        
        $this->awaitable = $awaitable;
    }
    
    /**
     * @return callable
     */
    public function getAwaitable(): Awaitable
    {
        return $this->awaitable;
    }
}
