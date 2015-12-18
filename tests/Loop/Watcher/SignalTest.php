<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop\Watcher;

use Icicle\Loop\{Manager\SignalManager, Watcher\Signal};
use Icicle\Tests\TestCase;

class SignalTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $manager;
    
    public function setUp()
    {
        $this->manager = $this->getMock(SignalManager::class);
    }
    
    public function createSignal($signo, callable $callback, $data = null)
    {
        return new Signal($this->manager, $signo, $callback, $data);
    }

    public function testGetSignal()
    {
        $signo = 1;

        $signal = $this->createSignal($signo, $this->createCallback(0));
        
        $this->assertSame($signo, $signal->getSignal());
    }
    
    public function testCall()
    {
        $signo = 1;

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo($signo), $this->isInstanceOf(Signal::class));

        $signal = $this->createSignal($signo, $callback);
        
        $signal->call();
        $signal->call();
    }
    
    /**
     * @depends testCall
     */
    public function testInvoke()
    {
        $signo = 1;

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo($signo));

        $signal = $this->createSignal($signo, $callback);

        $signal();
        $signal();
    }

    /**
     * @depends testCall
     */
    public function testSetCallback()
    {
        $signo = 1;

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo($signo));

        $signal = $this->createSignal($signo, $this->createCallback(0));

        $signal->setCallback($callback);

        $signal->call();
        $signal->call();
    }

    public function testEnable()
    {
        $signal = $this->createSignal(1, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('enable')
            ->with($this->identicalTo($signal));

        $signal->enable();
    }

    public function testIsEnabled()
    {
        $signal = $this->createSignal(1, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('isEnabled')
            ->with($this->identicalTo($signal))
            ->will($this->returnValue(true));

        $this->assertTrue($signal->isEnabled());
    }
    
    public function testDisable()
    {
        $signal = $this->createSignal(1, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('disable')
            ->with($this->identicalTo($signal));

        $signal->disable();
    }

    public function testUnreference()
    {
        $signal = $this->createSignal(1, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('unreference')
            ->with($this->identicalTo($signal));

        $signal->unreference();
    }

    public function testReference()
    {
        $signal = $this->createSignal(1, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('reference')
            ->with($this->identicalTo($signal));

        $signal->reference();
    }

    /**
     * @depends testReference
     * @depends testEnable
     */
    public function testExecuteAfterReference()
    {
        $signal = $this->createSignal(1, $this->createCallback(0));

        $this->manager->expects($this->exactly(2))
            ->method('reference')
            ->with($this->identicalTo($signal));

        $this->manager->expects($this->once())
            ->method('enable')
            ->with($this->identicalTo($signal));

        $signal->reference();

        $signal->enable();
    }

    public function testData()
    {
        $data = 'data';

        $signal = $this->createSignal(1, $this->createCallback(0), $data);

        $this->assertSame($data, $signal->getData());

        $signal->setData($data = 'test');

        $this->assertSame($data, $signal->getData());
    }
}
