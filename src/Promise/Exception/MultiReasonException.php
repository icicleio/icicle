<?php
namespace Icicle\Promise\Exception;

use Exception;

class MultiReasonException extends RuntimeException
{
    /**
     * @var Exception[]
     */
    private $reasons;
    
    /**
     * @param Exception[] $reasons Array of exceptions rejecting the promise.
     */
    public function __construct(array $reasons)
    {
        parent::__construct('Too many promises were rejected.');
        
        $this->reasons = $reasons;
    }
    
    /**
     * @return Exception[]
     */
    public function getReasons()
    {
        return $this->reasons;
    }
}
