<?php
namespace Icicle\Tests;

/**
 * Abstract test class with methods for creating callbacks and asserting runtimes.
 */
abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    const RUNTIME_PRECISION = 2; // Number of decimals to use in runtime calculations/comparisons.
    
    /**
     * Creates a callback that must be called $count times or the test will fail.
     *
     * @param   int $count Number of times the callback should be called.
     *
     * @return  callable Object that is callable.
     */
    public function createCallback($count)
    {
        $mock = $this->getMock('Icicle\Tests\Stub\CallbackStub');
        $mock->expects($this->exactly($count))
             ->method('__invoke');
        
        return $mock;
    }
    
    /**
     * Asserts that the given callback takes no more than $maxRunTime to run.
     *
     * @param   callable $callback
     * @param   float $maxRunTime
     */
    public function assertRunTimeLessThan(callable $callback, $maxRunTime, array $args = [])
    {
        $this->assertRunTimeBetween($callback, 0, $maxRunTime, $args);
    }
    
    /**
     * Asserts that the given callback takes more than $minRunTime to run.
     *
     * @param   callable $callback
     * @param   float $minRunTime
     */
    public function assertRunTimeGreaterThan(callable $callback, $minRunTime, array $args = [])
    {
        $this->assertRunTimeBetween($callback, $minRunTime, 0, $args);
    }
    
    /**
     * Asserts that the given callback takes between $minRunTime and $maxRunTime to execute.
     * Rounds to the nearest 100 ms.
     *
     * @param   callable $callback
     * @param   float $minRunTime
     * @param   float $maxRunTime
     */
    public function assertRunTimeBetween(callable $callback, $minRunTime, $maxRunTime, array $args = [])
    {
        $start = microtime(true);
        
        call_user_func_array($callback, $args);
        
        $runTime = round(microtime(true) - $start, self::RUNTIME_PRECISION);
        
        if (0 < $maxRunTime) {
            $this->assertLessThanOrEqual($maxRunTime, $runTime,
                "The run time of {$runTime}s was greater than the max run time of {$maxRunTime}s.");
        }
        
        if (0 < $minRunTime) {
            $this->assertGreaterThanOrEqual($minRunTime, $runTime,
                "The run time of {$runTime}s was less than the min run time of {$minRunTime}s.");
        }
    }
}
