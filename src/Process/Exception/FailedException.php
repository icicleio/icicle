<?php
namespace Icicle\Process\Exception;

use Icicle\Process\ProcessInterface;

class FailedException extends RuntimeException
{
    /**
     * @var ProcessInterface
     */
    private $process;
    
    public function __construct(ProcessInterface $process)
    {
        $this->process = $process;
        
        if (0 === $this->process->getExitCode()) {
            throw new InvalidArgumentException('Expected a failed process.');
        }
        
        parent::__construct('An error occurred while executing the process.');
    }
    
    /**
     * @return  ProcessInterface
     */
    public function getProcess()
    {
        return $this->process;
    }
}
