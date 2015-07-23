<?php
namespace Icicle\Tests\Promise;

use Exception;
use Icicle\Loop;
use Icicle\Promise;
use Icicle\Promise\PromiseInterface;
use Icicle\Tests\TestCase;

class PromiseMapTest extends TestCase
{
    public function tearDown()
    {
        Loop\clear();
    }
    
    public function testEmptyArray()
    {
        $values = [];
        
        $result = Promise\map($this->createCallback(0), $values);
        
        $this->assertSame($result, $values);
    }
    
    public function testValuesArray()
    {
        $values = [1, 2, 3];
        
        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return $value + 1;
        };
        
        $result = Promise\map($callback, $values);
        
        Loop\run();
        
        $this->assertTrue(is_array($result));
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertSame($values[$key] + 1, $promise->getResult());
        }
    }
    
    /**
     * @depends testValuesArray
     */
    public function testFulfilledPromisesArray()
    {
        $promises = [Promise\resolve(1), Promise\resolve(2), Promise\resolve(3)];
        
        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return $value + 1;
        };
        
        $result = Promise\map($callback, $promises);
        
        Loop\run();
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertSame($promises[$key]->getResult() + 1, $promise->getResult());
        }
    }
    
    /**
     * @depends testValuesArray
     */
    public function testPendingPromisesArray()
    {
        $promises = [
            Promise\resolve(1)->delay(0.2),
            Promise\resolve(2)->delay(0.3),
            Promise\resolve(3)->delay(0.1)
        ];
        
        $callback = $this->createCallback(3);
        $callback = function ($value) use ($callback) {
            $callback();
            return $value + 1;
        };
        
        $result = Promise\map($callback, $promises);
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }
        
        Loop\run();
        
        foreach ($result as $key => $promise) {
            $this->assertTrue($promise->isFulfilled());
            $this->assertSame($promises[$key]->getResult() + 1, $promise->getResult());
        }
    }
    
    /**
     * @depends testValuesArray
     */
    public function testRejectedPromisesArray()
    {
        $exception = new Exception();
        
        $promises = [
            Promise\reject($exception),
            Promise\reject($exception),
            Promise\reject($exception)
        ];
        
        $result = Promise\map($this->createCallback(0), $promises);
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertTrue($promise->isRejected());
            $this->assertSame($exception, $promise->getResult());
        }
    }
    
    /**
     * @depends testRejectedPromisesArray
     */
    public function testCallbackThrowingExceptionRejectsPromises()
    {
        $values = [1, 2, 3];
        $exception = new Exception();
        
        $callback = $this->createCallback(3);
        $callback->method('__invoke')
                 ->will($this->throwException($exception));
        
        $result = Promise\map($callback, $values);
        
        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }
        
        Loop\run();
        
        foreach ($result as $key => $promise) {
            $this->assertTrue($promise->isRejected());
            $this->assertSame($exception, $promise->getResult());
        }
    }

    public function testMultipleArrays()
    {
        $values1 = [1, 2, 3];
        $values2 = [3, 2, 1];

        $callback = $this->createCallback(3);
        $callback = function ($value1, $value2) use ($callback) {
            $callback();
            return $value1 + $value2;
        };

        $result = Promise\map($callback, $values1, $values2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }

        Loop\run();

        foreach ($result as $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertSame(4, $promise->getResult());
        }
    }

    /**
     * @depends testMultipleArrays
     */
    public function testMultipleArraysWithPromises()
    {
        $promises1 = [Promise\resolve(1), Promise\resolve(2), Promise\resolve(3)];
        $promises2 = [Promise\resolve(3), Promise\resolve(2), Promise\resolve(1)];

        $callback = $this->createCallback(3);
        $callback = function ($value1, $value2) use ($callback) {
            $callback();
            return $value1 + $value2;
        };

        $result = Promise\map($callback, $promises1, $promises2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }

        Loop\run();

        foreach ($result as $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertSame(4, $promise->getResult());
        }
    }

    /**
     * @depends testMultipleArrays
     */
    public function testMultipleArraysWithPendingPromises()
    {
        $promises1 = [
            Promise\resolve(1)->delay(0.2),
            Promise\resolve(2)->delay(0.3),
            Promise\resolve(3)->delay(0.1)
        ];
        $promises2 = [
            Promise\resolve(3)->delay(0.1),
            Promise\resolve(2)->delay(0.2),
            Promise\resolve(1)->delay(0.3)
        ];

        $callback = $this->createCallback(3);
        $callback = function ($value1, $value2) use ($callback) {
            $callback();
            return $value1 + $value2;
        };

        $result = Promise\map($callback, $promises1, $promises2);

        foreach ($result as $key => $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertTrue($promise->isPending());
        }

        Loop\run();

        foreach ($result as $promise) {
            $this->assertInstanceOf(PromiseInterface::class, $promise);
            $this->assertSame(4, $promise->getResult());
        }
    }

    /**
     * @depends testMultipleArrays
     */
    public function testMultipleArraysWithRejectedPromises()
    {
        $exception = new Exception();

        $promises1 = [
            Promise\reject($exception),
            Promise\resolve(2),
            Promise\reject($exception)
        ];
        $promises2 = [
            Promise\resolve(3),
            Promise\reject($exception),
            Promise\reject(new Exception())
        ];

        $callback = $this->createCallback(0);

        $result = Promise\map($callback, $promises1, $promises2);

        Loop\run();

        foreach ($result as $key => $promise) {
            $this->assertTrue($promise->isRejected());
            $this->assertSame($exception, $promise->getResult());
        }
    }
}
