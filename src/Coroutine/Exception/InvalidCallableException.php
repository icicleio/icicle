<?php
namespace Icicle\Coroutine\Exception;

use Exception;

class InvalidCallableException extends InvalidArgumentException
{
    /**
     * @var callable
     */
    private $callable;
    
    /**
     * @param   string $message
     * @param   callable $callable
     * @param   \Exception|null $previous
     */
    public function __construct($message, callable $callable, Exception $previous = null)
    {
        parent::__construct($message, 0, $previous);
        
        $this->callable = $callable;
    }
    
    /**
     * @return  callable
     */
    public function getCallable()
    {
        return $this->callable;
    }
}
