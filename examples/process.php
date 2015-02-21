<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use Icicle\Coroutine\Coroutine;
use Icicle\Loop\Loop;
use Icicle\Process\Process;

$process = new Process('php echo.php');

$promise = $process->run();

$coroutine = Coroutine::call(function (Process $process) {
    Loop::timer(10, function () use ($process) {
        $process->stop();
    });
    
    echo "PID: {$process->getPid()}\n";
    
    $process->getOutputStream()->write('test');
    
    while ($process->isRunning()) {
        $data = (yield $process->getOutputStream()->read());
        
        echo $data;
    }
    
    echo "Process done.\n";
}, $process);

$promise->then(
    function (Process $process) {
        echo "Exit code: {$process->getExitCode()}\n";
    },
    function (Exception $e) {
        echo "Process Error: {$e->getMessage()}\n";
        echo "Exit Code: {$e->getProcess()->getExitCode()}\n";
    }
);

$coroutine->then(
    function () {
        echo "Coroutine completed successfully.\n";
    },
    function (Exception $e) {
        echo "Coroutine Exception: {$e->getMessage()}\n";
    }
);

/* Loop::timer(10, function () {}); */

Loop::run();