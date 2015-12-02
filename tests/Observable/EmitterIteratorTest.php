<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Observable;

use Icicle\Awaitable;
use Icicle\Coroutine\Coroutine;
use Icicle\Loop;
use Icicle\Loop\SelectLoop;
use Icicle\Observable\Emitter;
use Icicle\Observable\EmitterIterator;
use Icicle\Tests\TestCase;

class EmitterIteratorTest extends TestCase
{
    const TIMEOUT = 0.1;

    /**
     * @var \Icicle\Observable\Emitter
     */
    protected $emitter;

    /**
     * @var \Icicle\Observable\EmitterIterator
     */
    protected $iterator;

    public function setUp()
    {
        Loop\loop(new SelectLoop());

        $this->emitter = new Emitter(function (callable $emit) {
            yield from $emit(Awaitable\resolve(1)->delay(self::TIMEOUT));
            yield from $emit(Awaitable\resolve(2)->delay(self::TIMEOUT));
            yield from $emit(Awaitable\resolve(3)->delay(self::TIMEOUT));
            yield 0;
        });

        $this->iterator = $this->emitter->getIterator();
    }

    protected function iterate(EmitterIterator $iterator, callable $callback)
    {
        while (yield $iterator->isValid()) {
            $callback($iterator->getCurrent());
        }
    }

    public function testIteration()
    {
        $coroutine = new Coroutine($this->iterate($this->iterator, $this->createCallback(3)));

        $coroutine->wait();
    }

    /**
     * @depends testIteration
     * @expectedException \Icicle\Observable\Exception\CompletedError
     */
    public function testGetCurrentThrowsWhenComplete()
    {
        $coroutine = new Coroutine($this->iterate($this->iterator, $this->createCallback(3)));

        $coroutine->wait();

        $this->iterator->getCurrent();
    }

    /**
     * @depends testIteration
     * @expectedException \Icicle\Observable\Exception\DisposedException
     */
    public function testGetCurrentThrowsWhenFailed()
    {
        $coroutine = new Coroutine($this->iterator->isValid());

        $this->emitter->dispose();

        $coroutine->wait();

        $this->iterator->getCurrent();
    }

    /**
     * @depends testIteration
     */
    public function testSimultaneousCallsToWait()
    {
        $coroutine1 = new Coroutine($this->iterator->isValid());
        $coroutine2 = new Coroutine($this->iterator->isValid());
        $coroutine3 = new Coroutine($this->iterator->isValid());
        $coroutine4 = new Coroutine($this->iterator->isValid());

        $this->assertTrue($coroutine1->wait());
        $this->assertTrue($coroutine2->wait());
        $this->assertTrue($coroutine3->wait());
        $this->assertFalse($coroutine4->wait());
    }

    /**
     * @expectedException \Icicle\Observable\Exception\UninitializedError
     */
    public function testCallGetCurrentBeforeWait()
    {
        $this->iterator->getCurrent();
    }

    /**
     * @expectedException \Icicle\Observable\Exception\IncompleteError
     */
    public function testGetReturnThrowsWhenIncomplete()
    {
        $coroutine = new Coroutine($this->iterator->isValid());

        $coroutine->wait();

        $this->iterator->getReturn();
    }

    /**
     * @expectedException \Icicle\Observable\Exception\IncompleteError
     */
    public function testGetReturnThrowsWhenFailed()
    {
        $coroutine = new Coroutine($this->iterator->isValid());

        $coroutine->wait();

        $this->emitter->dispose();

        $this->iterator->getReturn();
    }

    /**
     * @expectedException \Icicle\Observable\Exception\UninitializedError
     */
    public function testCallGetReturnBeforeWait()
    {
        $this->iterator->getReturn();
    }
}
