<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class PromiseAnyTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testEmptyArray()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\LogicException'));
        
        Promise::any([])->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Promise::any($values)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testPromisesArray()
    {
        $values = [1, 2, 3];
        $promises = [Promise::resolve(1), Promise::resolve(2), Promise::resolve(3)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1));
        
        Promise::any($promises)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testFulfillOnFirstInputPromiseFulfilled()
    {
        $exception = new Exception();
        $promises = [Promise::reject($exception), Promise::resolve(2), Promise::reject($exception)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(2));
        
        Promise::any($promises)->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    public function testRejectIfAllInputPromisesAreRejected()
    {
        $exception = new Exception();
        $promises = [Promise::reject($exception), Promise::reject($exception), Promise::reject($exception)];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\MultiReasonException'));
        
        Promise::any($promises)->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    public function testArrayKeysPreserved()
    {
        $exception = new Exception();
        $promises = [
            'one' => Promise::reject($exception),
            'two' => Promise::reject($exception),
            'three' => Promise::reject($exception)
        ];
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->callback(function ($exception) use ($promises) {
            $reasons = $exception->getReasons();
            ksort($reasons);
            ksort($promises);
            return array_keys($reasons) === array_keys($promises);
        }));
        
        Promise::any($promises)->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
}
