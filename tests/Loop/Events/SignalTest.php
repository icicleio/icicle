<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\Signal;
use Icicle\Loop\Manager\SignalManagerInterface;
use Icicle\Tests\TestCase;

class SignalTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $manager;
    
    public function setUp()
    {
        $this->manager = $this->getMock(SignalManagerInterface::class);
    }
    
    public function createSignal($signo, callable $callback)
    {
        return new Signal($this->manager, $signo, $callback);
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
            ->with($this->identicalTo($signo));

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
        $timer = $this->createSignal(1, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('enable')
            ->with($this->identicalTo($timer));

        $timer->enable();
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
        $timer = $this->createSignal(1, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('disable')
            ->with($this->identicalTo($timer));
        
        $timer->disable();
    }
}
