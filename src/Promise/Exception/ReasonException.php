<?php
namespace Icicle\Promise\Exception;

class ReasonException extends RuntimeException
{
    /**
     * @var mixed Reason for rejection.
     */
    private $reason;
    
    /**
     * @param   mixed $reason
     * @param   string $message
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
     * @return  mixed Rejection reason.
     */
    public function getReason()
    {
        return $this->reason;
    }
}
