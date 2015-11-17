<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Exception;

class MultiReasonException extends Exception
{
    /**
     * @var \Exception[]
     */
    private $reasons;
    
    /**
     * @param \Exception[] $reasons Array of exceptions rejecting the promise.
     */
    public function __construct(array $reasons)
    {
        parent::__construct('Too many promises were rejected.');
        
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
