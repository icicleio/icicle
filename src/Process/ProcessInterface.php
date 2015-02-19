<?php
namespace Icicle\Process;

interface ProcessInterface
{
    public function isRunning();
    
    public function stop($timeout = 10);
    
    public function signal($signo);
    
    public function getPid();
    
    public function getExitCode();
}
