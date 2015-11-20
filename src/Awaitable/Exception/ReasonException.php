<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Awaitable\Exception;

class ReasonException extends \Exception implements Exception
{
    /**
     * @var mixed Reason for rejection.
     */
    private $reason;
    
    /**
     * @param mixed $reason
     * @param string $message
     */
    public function __construct($reason, $message)
    {
        switch (gettype($reason)) {
            case 'object':
                if (!method_exists($reason, '__toString')) {
                    $message .= ' Reason: Object of type ' . get_class($reason);
                    break;
                } // next case handles object with __toString() method.

            case 'integer':
            case 'double':
            case 'string':
            case 'resource': // Can be converted to string.
                $message .= ' Reason: ' . $reason;
                break;
                
            case 'array':
                $message .= ' Reason: array(' . count($reason) . ')';
                break;
                
            case 'boolean':
                $message .= ' Reason: boolean(' . ($reason ? 'true' : 'false') . ')';
        }
        
        parent::__construct($message);
        
        $this->reason = $reason;
    }
    
    /**
     * @return mixed Rejection reason.
     */
    public function getReason()
    {
        return $this->reason;
    }
}
