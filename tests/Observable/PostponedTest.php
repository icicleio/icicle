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
use Icicle\Observable\Emitter;
use Icicle\Observable\Postponed;
use Icicle\Tests\TestCase;

class PostponedTestException extends \Exception {}

class PostponedTest extends TestCase
{
    /**
     * @var \Icicle\Observable\Postponed
     */
    protected $postponed;

    public function setUp()
    {
        Loop\loop(new SelectLoop());

        $this->postponed = new Postponed();
    }

    public function testGetEmitter()
    {
        $emitter = $this->postponed->getEmitter();

        $this->assertInstanceOf(Emitter::class, $emitter);
        $this->assertFalse($emitter->isComplete());
    }

    public function testComplete()
    {
        $value = 1;

        $this->postponed->complete($value);

        $emitter = $this->postponed->getEmitter();

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        $this->assertSame($value, $awaitable->wait());

        $this->assertTrue($emitter->isComplete());
        $this->assertFalse($emitter->isFailed());
    }

    public function testFail()
    {
        $exception = new PostponedTestException();

        $this->postponed->fail($exception);

        $emitter = $this->postponed->getEmitter();

        $awaitable = new Coroutine($emitter->each($this->createCallback(0)));

        try {
            $awaitable->wait();
        } catch (PostponedTestException $reason) {
            $this->assertTrue($emitter->isComplete());
            $this->assertTrue($emitter->isFailed());
        }
    }

    /**
     * @depends testGetEmitter
     */
    public function testEmit()
    {
        $value = 1;

        $emitter = $this->postponed->getEmitter();

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $observable = new Coroutine($emitter->each($callback));

        $coroutine = new Coroutine($this->postponed->emit($value));

        $this->assertSame($value, $coroutine->wait());
    }

    /**
     * @depends testEmit
     * @expectedException \Icicle\Observable\Exception\CompletedError
     */
    public function testEmitAfterComplete()
    {
        $emitter = $this->postponed->getEmitter();

        $observable = new Coroutine($emitter->each($this->createCallback(0)));

        $this->postponed->complete();

        $coroutine = new Coroutine($this->postponed->emit());

        $coroutine->wait();
    }

    /**
     * @depends testEmit
     * @expectedException \Icicle\Observable\Exception\CompletedError
     */
    public function testEmitAfterFail()
    {
        $exception = new PostponedTestException();

        $emitter = $this->postponed->getEmitter();

        $observable = new Coroutine($emitter->each($this->createCallback(0)));

        $this->postponed->fail($exception);

        $coroutine = new Coroutine($this->postponed->emit());

        $coroutine->wait();
    }
}
