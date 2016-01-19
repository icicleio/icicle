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
use Icicle\Observable;
use Icicle\Observable\Emitter;
use Icicle\Tests\TestCase;

class ConcatTestException extends \Exception {}

class ConcatTest extends TestCase
{
    public function testBasicConcat()
    {
        $count = 6;
        $observables = [];

        $observables[] = new Emitter(function (callable $emit) {
            yield $emit(1);
            yield $emit(2);
            yield $emit(3);
            return 4;
        });

        $observables[] = new Emitter(function (callable $emit) {
            yield $emit(4);
            yield $emit(5);
            yield $emit(6);
            return 8;
        });

        $observable = Observable\concat($observables);

        $i = 0;
        $callback = $this->createCallback($count);
        $callback->method('__invoke')
            ->will($this->returnCallback(function ($value) use (&$i) {
                $this->assertSame(++$i, $value);
            }));

        $awaitable = new Coroutine($observable->each($callback));

        $this->assertSame([4, 8], $awaitable->wait());
    }

    /**
     * @depends testBasicConcat
     */
    public function testConcatWithFailingObservable()
    {
        $reason = new ConcatTestException();
        $count = 3;
        $observables = [];

        $observables[] = Observable\of(1, 2, 3);
        $observables[] = Observable\fail($reason);

        $observable = Observable\concat($observables);

        $i = 0;
        $callback = $this->createCallback($count);
        $callback->method('__invoke')
            ->will($this->returnCallback(function ($value) use (&$i) {
                $this->assertSame(++$i, $value);
            }));

        $awaitable = new Coroutine($observable->each($callback));

        try {
            $awaitable->wait();
            $this->fail('Failing observable should fail observable returned from concat().');
        } catch (ConcatTestException $exception) {
            $this->assertSame($reason, $exception);
        }
    }
}
