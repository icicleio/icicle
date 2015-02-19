<?php
namespace Icicle\Process\Exception;

use Icicle\Process\ProcessInterface;

class TerminatedException extends RuntimeException
{
    /**
     * @var ProcessInterface
     */
    private $process;
    
    /**
     * @var int
     */
    private $signo;
    
    /**
     * @param   ProcessInterface $process
     * @param   int $signo
     */
    public function __construct(ProcessInterface $process, $signo)
    {
        $this->process = $process;
        $this->signo = (int) $signo;
        
        parent::__construct('The process was terminated.');
    }
    
    /**
     * @return  ProcessInterface
     */
    public function getProcess()
    {
        return $this->process;
    }
    
    /**
     * Signal used to terminate the process.
     *
     * @return  int
     */
    public function getSigno()
    {
        return $this->signo;
    }
}
