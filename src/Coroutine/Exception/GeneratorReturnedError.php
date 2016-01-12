<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine\Exception;

use Icicle\Exception\InvalidArgumentError;

class GeneratorReturnedError extends InvalidArgumentError implements Error
{
    /**
     * @var \Generator
     */
    private $generator;
    
    /**
     * @param \Generator $generator
     */
    public function __construct(\Generator $generator)
    {
        parent::__construct('Generator returned from Coroutine. Use "return yield from $coroutine;" to return the fulfillment value of $coroutine.');
        
        $this->generator = $generator;
    }
    
    /**
     * @return \Generator
     */
    public function getGenerator(): \Generator
    {
        return $this->generator;
    }
}
