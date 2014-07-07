<?php
namespace Icicle\Coroutine\Exception;

use Icicle\Coroutine\Coroutine;

class UnsuccessfulCancellationException extends RuntimeException
{
    /**
     * @var Coroutine
     */
    private $coroutine;
    
    /**
     * @param   Coroutine $coroutine
     */
    public function __construct(Coroutine $coroutine)
    {
        if ($coroutine->isPending()) {
            throw new InvalidArgumentException('Expected cancelled coroutine.');
        }
        
        parent::__construct('Generator still valid after coroutine cancellation.');
        
        $this->coroutine = $coroutine;
    }
    
    /**
     * @return  CoroutineInterface
     */
    public function getCoroutine()
    {
        return $this->coroutine;
    }
}
