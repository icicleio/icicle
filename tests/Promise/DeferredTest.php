<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Promise;
use Icicle\Promise\Deferred;
use Icicle\Promise\Exception\RejectedException;
use Icicle\Tests\TestCase;

class DeferredTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }
    
    public function testResolve()
    {
        $deferred = new Deferred();
        
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $deferred->getPromise()->done($callback);
        
        $deferred->resolve($value);
        
        Loop\run();
        
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
        
        Loop\run();
        
        $this->assertTrue($deferred->getPromise()->isRejected());
    }
    
    /**
     * @depends testReject
     */
    public function testRejectWithValueReason()
    {
        $deferred = new Deferred();
        
        $reason = 'String to reject promise.';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf(RejectedException::class));
        
        $deferred->getPromise()->done($this->createCallback(0), $callback);
        
        $deferred->reject($reason);
        
        Loop\run();
        
        $promise = $deferred->getPromise();
        
        $this->assertTrue($promise->isRejected());
        $this->assertSame($reason, $promise->getResult()->getReason());
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
        
        Loop\run();
        
        $this->assertTrue($deferred->getPromise()->isRejected());
        $this->assertSame($exception, $deferred->getPromise()->getResult());
    }
}
