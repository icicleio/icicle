<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Promise\Structures;

use Exception;
use Icicle\Loop;
use Icicle\Promise\Exception\RejectedException;
use Icicle\Promise\Promise;

class RejectedPromise extends ResolvedPromise
{
    /**
     * @var Exception
     */
    private $exception;
    
    /**
     * @param mixed $reason
     */
    public function __construct($reason)
    {
        if (!$reason instanceof Exception) {
            $reason = new RejectedException($reason);
        }
        
        $this->exception = $reason;
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null === $onRejected) {
            return $this;
        }
        
        return new Promise(function (callable $resolve, callable $reject) use ($onRejected) {
            Loop\queue(function () use ($resolve, $reject, $onRejected) {
                try {
                    $resolve($onRejected($this->exception));
                } catch (Exception $exception) {
                    $reject($exception);
                }
            });
        });
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        if (null !== $onRejected) {
            Loop\queue($onRejected, $this->exception);
        } else {
            Loop\queue(function () {
                throw $this->exception; // Rethrow exception in uncatchable way.
            });
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isCancelled()
    {
        return false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->exception;
    }
}
