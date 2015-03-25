<?php
namespace Icicle\Coroutine\Exception;

use Generator;

class InvalidGeneratorException extends RuntimeException
{
    /**
     * @var \Generator
     */
    private $generator;
    
    /**
     * @param   \Generator $generator
     */
    public function __construct(Generator $generator)
    {
        parent::__construct('Generator was not valid when initializing the coroutine.');
        
        $this->generator = $generator;
    }
    
    /**
     * @return  \Generator
     */
    public function getGenerator()
    {
        return $this->generator;
    }
}
