<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Promise\Exception\InvalidArgumentError;
use Icicle\Promise\Exception\MultiReasonException;
use Icicle\Tests\TestCase;

class PromiseSomeTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(InvalidArgumentError::class));
        
        Promise\some([], 1)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testRequireZeroFulfillsWithEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo([]));
        
        Promise\some([1], 0)->done($callback);
        
        Loop\run();
    }
    
    public function testValuesArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([1, 2]));
        
        Promise\some([1, 2, 3], 2)->done($callback);
        
        Loop\run();
    }
    
    public function testFulfilledPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [Promise\resolve(1), Promise\resolve(2), Promise\resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([0 => 1, 1 => 2]));
        
        Promise\some($promises, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testPendingPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [
            Promise\resolve(1)->delay(0.2),
            Promise\resolve(2)->delay(0.3),
            Promise\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([0 => 1, 2 => 3]));
        
        Promise\some($promises, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testRejectIfTooManyPromisesAreRejected()
    {
        $exception = new Exception();
        $promises = [Promise\reject($exception), Promise\resolve(2), Promise\reject($exception)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(MultiReasonException::class));
        
        Promise\some($promises, 2)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
    
    public function testFulfillImmediatelyWhenEnoughPromisesAreFulfilled()
    {
        $exception = new Exception();
        $promises = [
            Promise\reject($exception),
            Promise\resolve(2),
            Promise\reject($exception),
            Promise\resolve(4),
            Promise\resolve(5)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->equalTo([1 => 2, 3 => 4]));
        
        Promise\some($promises, 2)->done($callback);
        
        Loop\run();
    }
    
    public function testArrayKeysPreservedOnRejected()
    {
        $exception = new Exception();
        $promises = [
            'one' => Promise\reject($exception),
            'two' => Promise\resolve(2),
            'three' => Promise\reject($exception)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($exception) use ($promises) {
            $reasons = $exception->getReasons();
            ksort($reasons);
            return array_keys($reasons) === ['one', 'three'];
        }));
        
        Promise\some($promises, 2)->done($this->createCallback(0), $callback);
        
        Loop\run();
    }
}
