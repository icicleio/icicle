<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Observable;
use Icicle\Observable\Emitter;
use Icicle\Tests\TestCase;

class FromTestException extends \Exception {}

class FromTest extends TestCase
{
    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }

    public function testFromObservable()
    {
        $observable = new Emitter(function (callable $emit) {
            yield $emit(0);
        });

        $this->assertSame(Observable\from($observable), $observable);
    }

    /**
     * @return array
     */
    public function getTraversables()
    {
        return [
            [[1, 2, 3], 3],
            [['1' => 1, '2' => 2, '3' => 3, '4' => 4], 4],
            [new \ArrayObject([1, 2, 3]), 3],
            [new \ArrayIterator([1, 2, 3, 4]), 4],
            [$this->generator(10), 10],
        ];
    }

    /**
     * @return \Generator
     */
    protected function generator($count)
    {
        for ($i = 0; $i < $count; ++$i) {
            yield $i;
        }
    }

    /**
     * @dataProvider getTraversables
     *
     * @param array|\Traversable $traversable
     * @param int $count
     */
    public function testFrom($traversable, $count)
    {
        $observable = Observable\from($traversable);

        $awaitable = new Coroutine($observable->each($this->createCallback($count)));

        $awaitable->wait();
    }

    /**
     * @depends testFrom
     * @expectedException \Icicle\Tests\Observable\FromTestException
     */
    public function testFromIteratorThrows()
    {
        $exception = new FromTestException();

        $generator = function () use ($exception) {
            yield 1;
            throw $exception;
        };

        $observable = Observable\from($generator());

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));

        $awaitable->wait();
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return [
            ['test'],
            [3.14],
            [0],
            [new \stdClass()],
            [null],
        ];
    }

    /**
     * @dataProvider getValues
     *
     * @param mixed $value
     */
    public function testFromValue($value)
    {
        $observable = Observable\from($value);

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $awaitable = new Coroutine($observable->each($callback));
        $awaitable->done();

        Loop\run();
    }
}
