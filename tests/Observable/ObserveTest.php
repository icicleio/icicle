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
use Icicle\Observable\Exception\DisposedException;
use Icicle\Tests\TestCase;

class ObserveTest extends TestCase
{
    const TIMEOUT = 0.1;

    protected $callback;

    public function setUp()
    {
        Loop\loop(new SelectLoop());
    }

    protected function emit($count)
    {
        $callback = $this->callback;

        if (!is_callable($callback)) {
            $this->fail('Callback function not set.');
        }

        for ($i = 0; $i < $count; ++$i) {
            $callback(1, 2, 3);
        }
    }

    public function testBasicEmitter()
    {
        $emitter = function(callable $callback) {
            $this->callback = $callback;
        };

        $observable = Observable\observe($emitter);

        $callback = $this->createCallback(3);
        $callback->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        $awaitable = new Coroutine($observable->each($callback));

        Loop\run();

        $this->emit(3);

        $awaitable->done();

        Loop\run();

        $this->assertTrue($awaitable->isPending());
    }

    /**
     * @depends testBasicEmitter
     */
    public function testFunctionRequiringOtherArguments()
    {
        $value = 'data';

        $emitter = function($name, callable $callback) use ($value) {
            $this->callback = $callback;
            $this->assertSame($value, $name);
        };

        $observable = Observable\observe($emitter, null, 1, $value);

        $callback = $this->createCallback(3);
        $callback->method('__invoke')
            ->with($this->identicalTo([1, 2, 3]));

        $awaitable = new Coroutine($observable->each($callback));

        Loop\run();

        $this->emit(3);

        $awaitable->done();

        Loop\run();

        $this->assertTrue($awaitable->isPending());
    }

    /**
     * @depends testFunctionRequiringOtherArguments
     * @expectedException \Icicle\Exception\InvalidArgumentError
     */
    public function testTooFewArguments()
    {
        $observable = Observable\observe($this->createCallback(0), null, 1);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        $awaitable->wait();
    }

    /**
     * @depends testBasicEmitter
     */
    public function testDisposedFunction()
    {
        $emitter = function(callable $callback) {
            $this->callback = $callback;
        };

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->isType('callable'), $this->isInstanceOf(DisposedException::class));

        $observable = Observable\observe($emitter, $callback);

        $awaitable = new Coroutine($observable->each($this->createCallback(0)));

        Loop\tick();

        $observable->dispose();

        Loop\run();
    }
}
