<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine\Exception;

use Icicle\Exception\InvalidArgumentError;

class InvalidCallableError extends InvalidArgumentError implements Error
{
    /**
     * @var callable
     */
    private $callable;
    
    /**
     * @param callable $callable
     * @param \Exception $previous
     */
    public function __construct(callable $callable, \Exception $previous)
    {
        parent::__construct('The callable threw an exception or did not return a Generator.', 0, $previous);
        
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
