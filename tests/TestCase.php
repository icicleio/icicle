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
     * @return  callable Object that is callable and expects to be called the given number of times.
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
     * @param   mixed[]|null $args Function arguments.
     */
    public function assertRunTimeLessThan(callable $callback, $maxRunTime, array $args = null)
    {
        $this->assertRunTimeBetween($callback, 0, $maxRunTime, $args);
    }
    
    /**
     * Asserts that the given callback takes more than $minRunTime to run.
     *
     * @param   callable $callback
     * @param   float $minRunTime
     * @param   mixed[]|null $args Function arguments.
     */
    public function assertRunTimeGreaterThan(callable $callback, $minRunTime, array $args = null)
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
     * @param   mixed[]|null $args Function arguments.
     */
    public function assertRunTimeBetween(callable $callback, $minRunTime, $maxRunTime, array $args = null)
    {
        $start = microtime(true);
        
        call_user_func_array($callback, $args ?: []);
        
        $runTime = round(microtime(true) - $start, self::RUNTIME_PRECISION);
        
        if (0 < $maxRunTime) {
            $this->assertLessThanOrEqual(
                $maxRunTime,
                $runTime,
                sprintf('The run time of %.2fs was greater than the max run time of %.2fs.', $runTime, $maxRunTime)
            );
        }
        
        if (0 < $minRunTime) {
            $this->assertGreaterThanOrEqual(
                $minRunTime,
                $runTime,
                sprintf('The run time of %.2fs was less than the min run time of %.2fs.', $runTime, $minRunTime)
            );
        }
    }
}
