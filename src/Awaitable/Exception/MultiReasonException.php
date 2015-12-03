<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Exception;

class MultiReasonException extends \Exception implements Exception
{
    /**
     * @var \Exception[]
     */
    private $reasons;
    
    /**
     * @param \Exception[] $reasons Array of exceptions rejecting the promise.
     * @param string|null $message
     */
    public function __construct(array $reasons, $message = null)
    {
        parent::__construct($message ?: 'Too many awaitables were rejected.');
        
        $this->reasons = $reasons;
    }
    
    /**
     * @return \Exception[]
     */
    public function getReasons()
    {
        return $this->reasons;
    }
}
