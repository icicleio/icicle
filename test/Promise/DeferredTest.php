<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Deferred;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class DeferredTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testResolve()
    {
        $deferred = new Deferred();
        
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $deferred->getPromise()->done($callback, $this->createCallback(0));
        
        $deferred->resolve($value);
        
        Loop::run();
        
        $this->assertTrue($deferred->getPromise()->isFulfilled());
    }
    
    public function testReject()
    {
        $deferred = new Deferred();
        
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $deferred->getPromise()->done($this->createCallback(0), $callback);
        
        $deferred->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($deferred->getPromise()->isRejected());
    }
    
    public function testCancellation()
    {
        $exception = new Exception();
        
        $onCancelled = $this->createCallback(1);
        $onCancelled->method('__invoke')
                    ->with($this->identicalTo($exception));
        
        $deferred = new Deferred($onCancelled);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $deferred->getPromise()->done($this->createCallback(0), $callback);
        
        $deferred->getPromise()->cancel($exception);
        
        Loop::run();
        
        $this->assertTrue($deferred->getPromise()->isRejected());
        $this->assertSame($exception, $deferred->getPromise()->getResult());
    }
}
