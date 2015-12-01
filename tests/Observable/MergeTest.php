<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Awaitable\Delayed;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Observable;
use Icicle\Tests\TestCase;

class MergeTestException extends \Exception {}

class MergeTest extends TestCase
{
    const TIMEOUT = 0.1;

    protected $callback;

    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }

    protected function createEmitter($low, $high)
    {
        return new Observable\Emitter(function (callable $emit) use ($low, $high) {
            foreach (range($low, $high) as $value) {
                yield $emit($value);
            }
        });
    }

    public function getObservables()
    {
        return [
            [[range(1, 3), range(4, 6)], [1, 4, 2, 5, 3, 6]],
            [[$this->createEmitter(1, 3), range(4, 6)], [1, 4, 2, 5, 3, 6]],
            [[range(1, 5), $this->createEmitter(6, 8)], [1, 6, 2, 7, 3, 8, 4, 5]],
            [[new \ArrayObject(range(1, 4)), new \ArrayIterator(range(5, 8))], [1, 5, 2, 6, 3, 7, 4, 8]],
        ];
    }

    /**
     * @dataProvider getObservables
     *
     * @param array $observables
     * @param array $expected
     */
    public function testMerge(array $observables, array $expected)
    {
        $observable = Observable\merge($observables);

        $awaitable = new Coroutine($observable->each(function ($value) use ($expected) {
            static $i = 0;
            $this->assertSame($expected[$i++], $value);
        }));

        $awaitable->wait();
    }

    /**
     * @depends testMerge
     * @expectedException \Icicle\Tests\Observable\MergeTestException
     */
    public function testMergeWithFailedObservable()
    {
        $exception = new MergeTestException();

        $emitter = new Observable\Emitter(function (callable $emit) use ($exception) {
            yield $emit(1); // Emit once before failing.
            throw $exception;
        });

        $iterator = $this->getMock(Observable\ObservableIterator::class);
        $iterator->expects($this->once())
            ->method('wait')
            ->will($this->returnCallback(function () {
                yield new Delayed();
            }));

        $observable = $this->getMock(Observable\Observable::class);
        $observable->expects($this->once())
            ->method('getIterator')
            ->will($this->returnValue($iterator));
        $observable->expects($this->once())
            ->method('dispose')
            ->with($this->identicalTo($exception));

        $observable = Observable\merge([$emitter, $observable]);

        $awaitable = new Coroutine($observable->each($this->createCallback(1)));

        $awaitable->wait();
    }
}
