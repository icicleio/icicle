<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Exception\LogicException;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;
use InvalidArgumentException;
use RuntimeException;

/**
 * @requires PHP 5.4
 */
class PromiseTest extends TestCase
{
    protected $promise;
    
    protected $resolve;
    
    protected $reject;
    
    public function setUp()
    {
        $this->promise = new Promise(function ($resolve, $reject) {
            $this->resolve = $resolve;
            $this->reject = $reject;
        });
    }
    
    public function tearDown()
    {
        Loop::clear();
    }
    
    protected function resolve($value = null)
    {
        $resolve = $this->resolve;
        $resolve($value);
    }
    
    protected function reject($reason)
    {
        $reject = $this->reject;
        $reject($reason);
    }
    
    public function exceptionHandler(RuntimeException $exception) {}
    
    public function testResolverThrowingRejectsPromise()
    {
        $exception = new Exception();
        
        $promise = new Promise(function () use ($exception) {
            throw $exception;
        });
        
        $this->assertFalse($promise->isPending());
        $this->assertTrue($promise->isRejected());
        $this->assertSame($exception, $promise->getResult());
    }
    
    public function testThenReturnsPromise()
    {
        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $this->promise->then());
    }
    
    public function testResolve()
    {
        $this->assertSame($this->promise, Promise::resolve($this->promise));
        
        $value = 'test';
        $fulfilled = Promise::resolve($value);
        
        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $fulfilled);
        
        $this->assertFalse($fulfilled->isPending());
        $this->assertTrue($fulfilled->isFulfilled());
        $this->assertFalse($fulfilled->isRejected());
        $this->assertSame($value, $fulfilled->getResult());
    }
    
    public function testReject()
    {
        $exception = new Exception();
        
        $rejected = Promise::reject($exception);
        
        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $rejected);
        
        $this->assertFalse($rejected->isPending());
        $this->assertFalse($rejected->isFulfilled());
        $this->assertTrue($rejected->isRejected());
        $this->assertSame($exception, $rejected->getResult());
    }
    
    /**
     * @depends testReject
     */
    public function testRejectWithValueReason()
    {
        $reason = 'String to reject promise.';
        
        $rejected = Promise::reject($reason);
        
        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $rejected);
        
        $this->assertFalse($rejected->isPending());
        $this->assertFalse($rejected->isFulfilled());
        $this->assertTrue($rejected->isRejected());
        
        $result = $rejected->getResult();
        
        $this->assertInstanceOf('Icicle\Promise\Exception\RejectedException', $result);
        $this->assertSame($reason, $result->getReason());
    }
    
    /**
     * @depends testRejectWithValueReason
     */
    public function testRejectWithObjectReason()
    {
        $reason = new \stdClass();
        
        $rejected = Promise::reject($reason);
        
        $result = $rejected->getResult();
        
        $this->assertSame($reason, $result->getReason());
    }
    
    /**
     * @depends testRejectWithValueReason
     */
    public function testRejectWithArrayReason()
    {
        $reason = [1, 2, 3];
        
        $rejected = Promise::reject($reason);
        
        $result = $rejected->getResult();
        
        $this->assertSame($reason, $result->getReason());
    }
    
    /**
     * @depends testRejectWithValueReason
     */
    public function testRejectWithIntegerReason()
    {
        $reason = 404;
        
        $rejected = Promise::reject($reason);
        
        $result = $rejected->getResult();
        
        $this->assertSame($reason, $result->getReason());
    }
    
    /**
     * @depends testRejectWithValueReason
     */
    public function testRejectWithFloatReason()
    {
        $reason = 3.14159;
        
        $rejected = Promise::reject($reason);
        
        $result = $rejected->getResult();
        
        $this->assertSame($reason, $result->getReason());
    }
    
    /**
     * @depends testRejectWithValueReason
     */
    public function testRejectWithBooleanReason()
    {
        $reason = false;
        
        $rejected = Promise::reject($reason);
        
        $result = $rejected->getResult();
        
        $this->assertSame($reason, $result->getReason());
    }
    
    public function testResolveCallableWithValue()
    {
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $this->promise->done($callback, $this->createCallback(0));
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($this->promise->isFulfilled());
        $this->assertSame($value, $this->promise->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testThenReturnsPromiseAfterFulfilled()
    {
        $value = 'test';
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $this->promise->then());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testResolveMakesPromiseImmutable()
    {
        $value1 = 'test1';
        $value2 = 'test2';
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value1));
        
        $this->promise->done($callback, $this->createCallback(0));
        
        $this->resolve($value1);
        $this->resolve($value2);
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isFulfilled());
        $this->assertSame($value1, $this->promise->getResult());
    }
    
    /**
     * @depends testResolve
     * @depends testResolveCallableWithValue
     */
    public function testResolveCallableWithFulfilledPromise()
    {
        $value = 'test';
        $fulfilled = Promise::resolve($value);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $this->promise->done($callback, $this->createCallback(0));
        
        $this->resolve($fulfilled);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isFulfilled());
        $this->assertSame($value, $this->promise->getResult());
    }
    
    /**
     * @depends testReject
     */
    public function testResolveCallableWithRejectedPromise()
    {
        $exception = new Exception();
        $rejected = Promise::reject($exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $this->promise->done($this->createCallback(0), $callback);
        
        $this->resolve($rejected);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isRejected());
    }
    
    /**
     * @depends testResolveCallableWithFulfilledPromise
     * @depends testResolveCallableWithRejectedPromise
     */
    public function testResolveCallableWithPendingPromise()
    {
        $promise = new Promise(function () {});
        
        $this->resolve($promise);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isPending());
    }
    
    /**
     * @depends testResolveCallableWithPendingPromise
     */
    public function testResolveCallableWithPendingPromiseThenFulfillPendingPromise()
    {
        $value = 'test';
        
        $promise = new Promise(function ($resolve) use (&$pendingResolve) {
            $pendingResolve = $resolve;
        });
        
        $this->resolve($promise);
        
        Loop::run();
        
        $pendingResolve($value);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isFulfilled());
        $this->assertSame($value, $this->promise->getResult());
        $this->assertSame($this->promise->getResult(), $promise->getResult());
    }
    
    /**
     * @depends testResolveCallableWithPendingPromise
     */
    public function testResolveCallableWithPendingPromiseThenRejectPendingPromise()
    {
        $exception = new Exception();
        
        $promise = new Promise(function ($resolve, $reject) use (&$pendingReject) {
            $pendingReject = $reject;
        });
        
        $this->resolve($promise);
        
        Loop::run();
        
        $pendingReject($exception);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isRejected());
        $this->assertSame($exception, $this->promise->getResult());
        $this->assertSame($this->promise->getResult(), $promise->getResult());
    }
    
    /**
     * @depends testResolveCallableWithPendingPromise
     */
    public function testResolveCallableWithSelfRejectsPromise()
    {
        $this->resolve($this->promise);
        
        $this->assertTrue($this->promise->isRejected());
        $this->assertInstanceOf('Icicle\Promise\Exception\TypeException', $this->promise->getResult());
    }
    
    /**
     * @depends testResolveCallableWithSelfRejectsPromise
     */
    public function testResolveWithCircularReferenceRejectsPromise()
    {
        $promise = new Promise(function ($resolve) use (&$pendingResolve) {
            $pendingResolve = $resolve;
        });
        
        $child = $this->promise->then(function () use ($promise) {
            return $promise;
        });
        
        $pendingResolve($child);
        
        Loop::run();
        
        $this->resolve();
        
        Loop::run();
        
        $this->assertTrue($child->isRejected());
        $this->assertInstanceOf('Icicle\Promise\Exception\TypeException', $child->getResult());
        
        $this->assertTrue($promise->isRejected());
        $this->assertInstanceOf('Icicle\Promise\Exception\TypeException', $promise->getResult());
    }
    
    public function testRejectCallable()
    {
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $this->promise->done($this->createCallback(0), $callback);
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($this->promise->isRejected());
        $this->assertSame($exception, $this->promise->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testRejectCallableWithValueReason()
    {
        $reason = 'String to reject promise.';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\RejectedException'));
        
        $this->promise->done($this->createCallback(0), $callback);
        
        $this->reject($reason);
        
        Loop::run();
        
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($this->promise->isRejected());
        $this->assertSame($reason, $this->promise->getResult()->getReason());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testThenReturnsPromiseAfterRejected()
    {
        $exception = new Exception();
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $this->promise->then());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testRejectMakesPromiseImmutable()
    {
        $exception1 = new Exception();
        $exception2 = new Exception();
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception1));
        
        $this->promise->done($this->createCallback(0), $callback);
        
        $this->reject($exception1);
        $this->resolve($value);
        $this->reject($exception2);
        
        Loop::run();
        
        $this->assertTrue($this->promise->isRejected());
        $this->assertSame($exception1, $this->promise->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testThenInvokeNewCallbackAfterResolved()
    {
        $value = 'test';
        $this->resolve($value);
        
        Loop::run();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $this->promise->then($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testDoneInvokeNewCallbackAfterResolved()
    {
        $value = 'test';
        $this->resolve($value);
        
        Loop::run();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $this->promise->done($callback, $this->createCallback(0));
        
        Loop::run();
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testThenInvokeNewCallbackAfterRejected()
    {
        $exception = new Exception();
        $this->reject($exception);
        
        Loop::run();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $this->promise->then($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testDoneInvokeNewCallbackAfterRejected()
    {
        $exception = new Exception();
        $this->reject($exception);
        
        Loop::run();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $this->promise->done($this->createCallback(0), $callback);
        
        Loop::run();
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testChildPromiseResolutionWithNoCallbacks()
    {
        $value = 'test';
        
        $child = $this->promise->then();
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testChildPromiseFulfilledWithOnFulfilledReturnValueWithThenBeforeResolve()
    {
        $value1 = 'test';
        $value2 = 1;
        
        $callback = function ($value) use (&$parameter, $value2) {
            $parameter = $value;
            return $value2;
        };
        
        $child = $this->promise->then($callback, $this->createCallback(0));
        
        $this->resolve($value1);
        
        Loop::run();
        
        $this->assertSame($value1, $parameter);
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value2, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testChildPromiseFulfilledWithOnRejectedReturnValueWithThenBeforeReject()
    {
        $exception = new Exception();
        $value = 1;
        
        $callback = function ($e) use (&$parameter, $value) {
            $parameter = $e;
            return $value;
        };
        
        $child = $this->promise->then($this->createCallback(0), $callback);
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertSame($exception, $parameter);
        $this->assertTrue($child->isFulFilled());
        $this->assertSame($value, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testChildPromiseRejectedWithOnFulfilledThrownExceptionWithThenBeforeResolve()
    {
        $value = 'test';
        $exception = new Exception();
        
        $callback = function ($value) use (&$parameter, $exception) {
            $parameter = $value;
            throw $exception;
        };
        
        $child = $this->promise->then($callback, $this->createCallback(0));
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertSame($value, $parameter);
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testChildPromiseRejectedWithOnRejectedThrownExceptionWithThenBeforeReject()
    {
        $exception1 = new Exception('Test Exception 1.');
        $exception2 = new Exception('Test Exception 2.');
        
        $callback = function ($e) use (&$parameter, $exception2) {
            $parameter = $e;
            throw $exception2;
        };
        
        $child = $this->promise->then($this->createCallback(0), $callback);
        
        $this->reject($exception1);
        
        Loop::run();
        
        $this->assertSame($exception1, $parameter);
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception2, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testChildPromiseFulfilledWithOnFulfilledReturnValueWithThenAfterResolve()
    {
        $value1 = 'test';
        $value2 = 1;
        
        $callback = function ($value) use (&$parameter, $value2) {
            $parameter = $value;
            return $value2;
        };
        
        $this->resolve($value1);
        
        $child = $this->promise->then($callback, $this->createCallback(0));
        
        Loop::run();
        
        $this->assertSame($value1, $parameter);
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value2, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testChildPromiseFulfilledWithOnRejectedReturnValueWithThenAfterReject()
    {
        $exception = new Exception();
        $value = 1;
        
        $callback = function ($e) use (&$parameter, $value) {
            $parameter = $e;
            return $value;
        };
        
        $this->reject($exception);
        
        $child = $this->promise->then($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertSame($exception, $parameter);
        $this->assertTrue($child->isFulFilled());
        $this->assertSame($value, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testChildPromiseRejectedWithOnFulfilledThrownExceptionWithThenAfterResolve()
    {
        $value = 'test';
        $exception = new Exception();
        
        $callback = function ($value) use (&$parameter, $exception) {
            $parameter = $value;
            throw $exception;
        };
        
        $this->resolve($value);
        
        $child = $this->promise->then($callback, $this->createCallback(0));
        
        Loop::run();
        
        $this->assertSame($value, $parameter);
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testChildPromiseRejectedWithOnRejectedThrownExceptionWithThenAfterReject()
    {
        $exception1 = new Exception('Test Exception 1.');
        $exception2 = new Exception('Test Exception 2.');
        
        $callback = function ($e) use (&$parameter, $exception2) {
            $parameter = $e;
            throw $exception2;
        };
        
        $this->reject($exception1);
        
        $child = $this->promise->then($this->createCallback(0), $callback);
        
        Loop::run();
        
        $this->assertSame($exception1, $parameter);
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception2, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testFulfilledPromiseFallThroughWithNoOnFulfilled()
    {
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $child = $this->promise->then(null, $this->createCallback(0));
        
        $grandchild = $child->then($callback, $this->createCallback(0));
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value, $child->getResult());
        $this->assertFalse($grandchild->isPending());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testRejectedPromiseFallThroughWithNoOnRejected()
    {
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $child = $this->promise->then($this->createCallback(0));
        
        $grandchild = $child->then($this->createCallback(0), $callback);
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception, $child->getResult());
        $this->assertFalse($grandchild->isPending());
    }
    
    /**
     * @depends testResolveCallableWithValue
     * @depends testChildPromiseFulfilledWithOnFulfilledReturnValueWithThenBeforeResolve
     */
    public function testChildPromiseResolvedWithPromiseReturnedByOnFulfilled()
    {
        $value1 = 'test1';
        $value2 = 'test2';
        
        $callback = function ($value) use (&$parameter, $value2) {
            $parameter = $value;
            return Promise::resolve($value2);
        };
        
        $child = $this->promise->then($callback, $this->createCallback(0));
        
        $this->resolve($value1);
        
        Loop::run();
        
        $this->assertSame($value1, $parameter);
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value2, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     * @depends testChildPromiseFulfilledWithOnFulfilledReturnValueWithThenBeforeResolve
     */
    public function testChildPromiseRejectedWithPromiseReturnedByOnFulfilled()
    {
        $value = 'test';
        $exception = new Exception();
        
        $callback = function ($value) use (&$parameter, $exception) {
            $parameter = $value;
            return Promise::reject($exception);
        };
        
        $child = $this->promise->then($callback, $this->createCallback(0));
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertSame($value, $parameter);
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     * @depends testChildPromiseFulfilledWithOnRejectedReturnValueWithThenBeforeReject
     */
    public function testChildPromiseResolvedWithPromiseReturnedByOnRejected()
    {
        $value = 'test';
        $exception = new Exception();
        
        $callback = function ($exception) use (&$parameter, $value) {
            $parameter = $exception;
            return Promise::resolve($value);
        };
        
        $child = $this->promise->then($this->createCallback(0), $callback);
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertSame($exception, $parameter);
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     * @depends testChildPromiseFulfilledWithOnRejectedReturnValueWithThenBeforeReject
     */
    public function testChildPromiseRejectedWithPromiseReturnedByOnRejected()
    {
        $exception1 = new Exception();
        $exception2 = new Exception();
        
        $callback = function ($exception) use (&$parameter, $exception2) {
            $parameter = $exception;
            return Promise::reject($exception2);
        };
        
        $child = $this->promise->then($this->createCallback(0), $callback);
        
        $this->reject($exception1);
        
        Loop::run();
        
        $this->assertSame($exception1, $parameter);
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception2, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     * @expectedException Icicle\Promise\Exception\LogicException
     */
    public function testDoneNoOnRejectedThrowsUncatchableExceptionWithRejectionAfter()
    {
        $exception = new LogicException();
        
        $this->promise->done($this->createCallback(0));
        
        $this->reject($exception);
        
        Loop::run(); // Exception will be thrown from loop.
    }
    
    /**
     * @depends testRejectCallable
     * @expectedException Icicle\Promise\Exception\LogicException
     */
    public function testDoneNoOnRejectedThrowsUncatchableExceptionWithRejectionBefore()
    {
        $exception = new LogicException();
        
        $this->reject($exception);
        
        $this->promise->done($this->createCallback(0));
        
        Loop::run(); // Exception will be thrown from loop.
    }
    
    /**
     * @depends testThenInvokeNewCallbackAfterResolved
     * @depends testDoneInvokeNewCallbackAfterResolved
     */
    public function testCallToOnFulfilledIsAsynchronous()
    {
        $this->resolve();
        
        Loop::run();
        
        $this->assertTrue($this->promise->isFulfilled());
        
        $string = '';
        
        $callback = function () use (&$string) {
            $string .= '<onFulfilled>';
        };
        
        $string .= '<before>';
        
        $this->promise->then($callback);
        $this->promise->done($callback);
        
        $string .= '<after>';
        
        Loop::run();
        
        $this->assertSame('<before><after><onFulfilled><onFulfilled>', $string);
    }
    
    /**
     * @depends testThenInvokeNewCallbackAfterRejected
     * @depends testDoneInvokeNewCallbackAfterRejected
     */
    public function testCallToOnRejectedIsAsynchronous()
    {
        $this->reject(new Exception());
        
        Loop::run();
        
        $this->assertTrue($this->promise->isRejected());
        
        $string = '';
        
        $callback = function () use (&$string) {
            $string .= '<onRejected>';
        };
        
        $string .= '<before>';
        
        $this->promise->then(null, $callback);
        $this->promise->done(null, $callback);
        
        $string .= '<after>';
        
        Loop::run();
        
        $this->assertSame('<before><after><onRejected><onRejected>', $string);
    }
    
    /**
     * @expectedException Icicle\Promise\Exception\UnresolvedException
     */
    public function testGettingResultBeforeResolution()
    {
        $this->promise->getResult();
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testCapture()
    {
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $child = $this->promise->capture($callback);
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($child->isFulfilled());
        $this->assertNull($child->getResult());
    }
    
    /**
     * @depends testCapture
     */
    public function testCaptureWithTypeHint()
    {
        $value = 'test';
        
        $child1 = $this->promise->capture(function (InvalidArgumentException $exception) {});
        $child2 = $this->promise->capture(function (RuntimeException $exception) use ($value) { return $value; });
        $child3 = $this->promise->capture([$this, 'exceptionHandler']); // Typehinted method.
        $child4 = $child1->capture(function (RuntimeException $exception) use ($value) { return $value; });
        
        $exception = new RuntimeException();
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($child1->isRejected());
        $this->assertSame($exception, $child1->getResult());
        
        $this->assertTrue($child2->isFulfilled());
        $this->assertSame($value, $child2->getResult());
        
        $this->assertTrue($child3->isFulfilled());
        $this->assertNull($child3->getResult());
        
        $this->assertTrue($child4->isFulfilled());
        $this->assertSame($value, $child4->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testTapAfterFulfilled()
    {
        $value = 'test';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($value));
        
        $child = $this->promise->tap($callback);
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testTapAfterRejected()
    {
        $exception = new Exception();
        
        $child = $this->promise->tap($this->createCallback(0));
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception, $child->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testCleanupAfterFulfilled()
    {
        $value = 'test';
        
        $child = $this->promise->cleanup($this->createCallback(1));
        
        $this->resolve($value);
        
        Loop::run();
        
        $this->assertTrue($child->isFulfilled());
        $this->assertSame($value, $child->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testCleanupAfterRejected()
    {
        $exception = new Exception();
        
        $child = $this->promise->cleanup($this->createCallback(1));
        
        $this->reject($exception);
        
        Loop::run();
        
        $this->assertTrue($child->isRejected());
        $this->assertSame($exception, $child->getResult());
    }
    
    public function testCancellation()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\CancelledException'));
        
        $promise = new Promise(function () {}, $callback);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\CancelledException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $promise->cancel();
        
        Loop::run();
    }
    
    /**
     * @depends testCancellation
     */
    public function testOnCancelledThrowsException()
    {
        $exception = new Exception();
        
        $onCancelled = function () use ($exception) {
            throw $exception;
        };
        
        $promise = new Promise(function () {}, $onCancelled);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        $promise->cancel();
        
        Loop::run();
        
        $this->assertTrue($promise->isRejected());
        $this->assertSame($exception, $promise->getResult());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellationWithSpecificException()
    {
        $exception = new Exception();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise = new Promise(function () {}, $callback);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $promise->done($this->createCallback(0), $callback);
        
        $promise->cancel($exception);
        
        Loop::run();
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellationWithValueReason()
    {
        $reason = 'String to cancel promise.';
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\CancelledException'));
        
        $promise = new Promise(function () {}, $callback);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\CancelledException'));
        
        $promise->done($this->createCallback(0), $callback);
        
        $promise->cancel($reason);
        
        Loop::run();
        
        $this->assertSame($reason, $promise->getResult()->getReason());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellingParentRejectsChild()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\CancelledException'));
        
        $child = $this->promise->then();
        $child->done(null, $callback);
        
        $this->promise->cancel();
        
        Loop::run();
        
        $this->assertTrue($this->promise->isRejected());
        $this->assertTrue($child->isRejected());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellingOnlyChildCancelsParent()
    {
        $child = $this->promise->then();
        
        $child->cancel();
        
        Loop::run();
        
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($this->promise->isRejected());
        $this->assertTrue($child->isRejected());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellingSiblingChildDoesNotCancelParent()
    {
        $child1 = $this->promise->then();
        $child2 = $this->promise->then();
        
        $child1->cancel();
        
        Loop::run();
        
        $this->assertTrue($this->promise->isPending());
        $this->assertTrue($child1->isRejected());
        $this->assertTrue($child2->isPending());
    }
    
    /**
     * @depends testCancellingSiblingChildDoesNotCancelParent
     */
    public function testCancellingAllChildrenCancelsParent()
    {
        $child1 = $this->promise->then();
        $child2 = $this->promise->then();
        
        $child1->cancel();
        $child2->cancel();
        
        Loop::run();
        
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($child1->isRejected());
        $this->assertTrue($child2->isRejected());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancellingParentCancelsAllChildren()
    {
        $child1 = $this->promise->then();
        $child2 = $this->promise->then();
        
        $this->promise->cancel();
        
        Loop::run();
        
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($child1->isRejected());
        $this->assertTrue($child2->isRejected());
    }

    /**
     * @depends testCancellation
     */
    public function testCancellingSiblingsThenCreateSiblingPromise()
    {
        $child1 = $this->promise->then();
        $child2 = $this->promise->then();

        $child1->cancel();
        $child2->cancel();

        $child3 = $this->promise->then();

        Loop::run();

        $this->assertTrue($this->promise->isPending());
        $this->assertTrue($child3->isPending());
    }
    
    /**
     * @depends testResolveCallableWithPendingPromise
     */
    public function testCancellationAfterResolvingWithPendingPromise()
    {
        $exception = new Exception();
        
        $promise = new Promise(function () {});
        
        $this->resolve($promise);
        
        $this->promise->cancel($exception);
        
        Loop::run();
        
        $this->assertTrue($promise->isRejected());
        $this->assertSame($exception, $promise->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testDelayThenFulfill()
    {
        $value = 'test';
        $time = 0.1;
        
        $delayed = $this->promise->delay($time);
        
        $this->resolve($value);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($delayed->isFulfilled());
        $this->assertSame($value, $delayed->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testDelayThenReject()
    {
        $exception = new Exception();
        $time = 0.1;
        
        $delayed = $this->promise->delay($time);
        
        $this->reject($exception);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($delayed->isRejected());
        $this->assertSame($exception, $delayed->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testDelayAfterFulfilled()
    {
        $value = 'test';
        $time = 0.1;
        
        $this->resolve($value);
        
        $delayed = $this->promise->delay($time);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($delayed->isFulfilled());
        $this->assertSame($value, $delayed->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testDelayAfterRejected()
    {
        $exception = new Exception();
        $time = 0.1;
        
        $this->reject($exception);
        
        $delayed = $this->promise->delay($time);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($delayed->isRejected());
        $this->assertSame($exception, $delayed->getResult());
    }
    
    /**
     * @depends testResolveCallableWithPendingPromise
     */
    public function testDelayAfterResolvingWithPendingPromise()
    {
        $value = 'test';
        $time = 0.1;
        
        $promise = new Promise(function ($resolve) use (&$pendingResolve) {
            $pendingResolve = $resolve;
        });
        
        $this->resolve($promise);
        
        $delayed = $this->promise->delay($time);
        
        $pendingResolve($value);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($delayed->isFulfilled());
        $this->assertSame($value, $delayed->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     * @depends testCancellation
     */
    public function testCancelDelayBeforeFulfilled()
    {
        $value = 'test';
        $time = 0.1;
        
        $delayed = $this->promise->delay($time);
        
        $this->resolve($value);
        
        Loop::tick(false);
        
        $delayed->cancel();
        
        $this->assertTrue($delayed->isRejected());
        $this->assertTrue($this->promise->isFulfilled());
    }
    
    /**
     * @depends testResolveCallableWithValue
     * @depends testCancellation
     */
    public function testCancelDelayAfterFulfilled()
    {
        $value = 'test';
        $time = 0.1;
        
        $this->resolve($value);
        
        $delayed = $this->promise->delay($time);
        
        Loop::tick(false);
        
        $delayed->cancel();
        
        $this->assertTrue($delayed->isRejected());
        $this->assertTrue($this->promise->isFulfilled());
    }

    /**
     * @depends testCancellation
     */
    public function testCancelDelay()
    {
        $time = 0.1;
        
        $delayed = $this->promise->delay($time);
        
        $delayed->cancel();
        
        Loop::run();
        
        $this->assertTrue($delayed->isRejected());
        $this->assertTrue($this->promise->isRejected());
    }
    
    /**
     * @depends testCancellation
     */
    public function testCancelDelayWithSiblingPromise()
    {
        $time = 0.1;
        
        $delayed = $this->promise->delay($time);
        $sibling = $this->promise->then();
        
        $delayed->cancel();
        
        Loop::run();
        
        $this->assertTrue($delayed->isRejected());
        $this->assertTrue($this->promise->isPending());
        $this->assertTrue($sibling->isPending());
    }

    /**
     * @depends testCancellation
     */
    public function testCancelDelayAndCancelSiblingPromise()
    {
        $time = 0.1;

        $delayed = $this->promise->delay($time);
        $sibling = $this->promise->then();

        $delayed->cancel();
        $sibling->cancel();

        Loop::run();

        $this->assertTrue($delayed->isRejected());
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($sibling->isRejected());
    }

    /**
     * @depends testCancellation
     */
    public function testCancelDelayThenCreateSiblingPromise()
    {
        $time = 0.1;

        $delayed = $this->promise->delay($time);

        $delayed->cancel();

        $sibling = $this->promise->then();

        Loop::run();

        $this->assertTrue($delayed->isRejected());
        $this->assertTrue($this->promise->isPending());
        $this->assertTrue($sibling->isPending());
    }
    
    /**
     * @depends testCancellation
     */
    public function testTimeout()
    {
        $time = 0.1;
        
        $timeout = $this->promise->timeout($time);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\TimeoutException'));
        
        $timeout->done($this->createCallback(0), $callback);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($this->promise->isRejected());
        $this->assertInstanceOf('Icicle\Promise\Exception\TimeoutException', $this->promise->getResult());
    }
    
    /**
     * @depends testTimeout
     */
    public function testTimeoutWithSpecificException()
    {
        $time = 0.1;
        $exception = new Exception();
        
        $timeout = $this->promise->timeout($time, $exception);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($exception));
        
        $timeout->done($this->createCallback(0), $callback);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', $time);
    }
    
    /**
     * @depends testTimeout
     */
    public function testTimeoutWithValueReason()
    {
        $time = 0.1;
        $reason = 'String to timeout promise.';
        
        $timeout = $this->promise->timeout($time, $reason);
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->isInstanceOf('Icicle\Promise\Exception\TimeoutException'));
        
        $timeout->done($this->createCallback(0), $callback);
        
        $this->assertRunTimeGreaterThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertSame($reason, $this->promise->getResult()->getReason());
        $this->assertSame($reason, $timeout->getResult()->getReason());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testTimeoutThenFulfill()
    {
        $value = 'test';
        $time = 0.1;
        
        $timeout = $this->promise->timeout($time);
        
        $this->resolve($value);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($timeout->isFulfilled());
        $this->assertSame($value, $timeout->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testTimeoutThenReject()
    {
        $exception = new Exception();
        $time = 0.1;
        
        $timeout = $this->promise->timeout($time);
        
        $this->reject($exception);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($timeout->isRejected());
        $this->assertSame($exception, $timeout->getResult());
    }
    
    /**
     * @depends testResolveCallableWithValue
     */
    public function testTimeoutAfterFulfilled()
    {
        $value = 'test';
        $time = 0.1;
        
        $this->resolve($value);
        
        $timeout = $this->promise->timeout($time);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($timeout->isFulfilled());
        $this->assertSame($value, $timeout->getResult());
    }
    
    /**
     * @depends testRejectCallable
     */
    public function testTimeoutAfterRejected()
    {
        $exception = new Exception();
        $time = 0.1;
        
        $this->reject($exception);
        
        $timeout = $this->promise->timeout($time);
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($timeout->isRejected());
        $this->assertSame($exception, $timeout->getResult());
    }
    
    /**
     * @depends testTimeout
     * @depends testCancellation
     */
    public function testCancelTimeout()
    {
        $time = 0.1;
        
        $timeout = $this->promise->timeout($time);
        
        $timeout->cancel();
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($timeout->isRejected());
        $this->assertTrue($this->promise->isRejected());
    }
    
    /**
     * @depends testCancelTimeout
     */
    public function testCancelTimeoutOnSibling()
    {
        $time = 0.1;
        
        $timeout = $this->promise->timeout($time);
        $sibling = $this->promise->then();
        
        $timeout->cancel();
        
        $this->assertRunTimeLessThan('Icicle\Loop\Loop::run', $time);
        
        $this->assertTrue($timeout->isRejected());
        $this->assertTrue($this->promise->isPending());
        $this->assertTrue($sibling->isPending());
    }

    /**
     * @depends testCancellation
     */
    public function testCancelTimeoutAndCancelSiblingPromise()
    {
        $time = 0.1;

        $timeout = $this->promise->timeout($time);
        $sibling = $this->promise->then();

        $timeout->cancel();
        $sibling->cancel();

        Loop::run();

        $this->assertTrue($timeout->isRejected());
        $this->assertFalse($this->promise->isPending());
        $this->assertTrue($sibling->isRejected());
    }

    /**
     * @depends testCancellation
     */
    public function testCancelTimeoutThenCreateSiblingPromise()
    {
        $time = 0.1;

        $timeout = $this->promise->delay($time);

        $timeout->cancel();

        $sibling = $this->promise->then();

        Loop::run();

        $this->assertTrue($timeout->isRejected());
        $this->assertTrue($this->promise->isPending());
        $this->assertTrue($sibling->isPending());
    }
}
