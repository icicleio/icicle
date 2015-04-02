<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Tests\TestCase;

/**
 * @requires PHP 5.4
 */
class PromiseAdaptTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testThenCalled()
    {
        $mock = $this->getMock('Icicle\Promise\PromiseInterface');

        $mock->expects($this->once())
            ->method('then')
            ->with(
                $this->callback(function ($resolve) {
                    return is_callable($resolve);
                }),
                $this->callback(function ($reject) {
                    return is_callable($reject);
                })
            );

        $promise = Promise::adapt($mock);

        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $promise);

        $promise->done($this->createCallback(0), $this->createCallback(0));

        Loop::run();
    }

    /**
     * @depends testThenCalled
     */
    public function testThenableFulfilled()
    {
        $value = 1;

        $mock = $this->getMock('Icicle\Promise\PromiseInterface');

        $mock->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($resolve, $reject) use ($value) {
                $resolve($value);
            }));

        $promise = Promise::adapt($mock);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $promise->done($callback, $this->createCallback(0));

        Loop::run();
    }

    /**
     * @depends testThenCalled
     */
    public function testThenableRejected()
    {
        $reason = 'Rejected';

        $mock = $this->getMock('Icicle\Promise\PromiseInterface');

        $mock->expects($this->once())
            ->method('then')
            ->will($this->returnCallback(function ($resolve, $reject) use ($reason) {
                $reject($reason);
            }));

        $promise = Promise::adapt($mock);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Promise\Exception\ExceptionInterface'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    public function testScalarValue()
    {
        $value = 1;

        $promise = Promise::adapt($value);

        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $promise);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Promise\Exception\TypeException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }

    public function testNonThenableObject()
    {
        $object = new \stdClass();

        $promise = Promise::adapt($object);

        $this->assertInstanceOf('Icicle\Promise\PromiseInterface', $promise);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isInstanceOf('Icicle\Promise\Exception\TypeException'));

        $promise->done($this->createCallback(0), $callback);

        Loop::run();
    }
}
