<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop\Watcher;

use Icicle\Loop\{Manager\ImmediateManager, Watcher\Immediate};
use Icicle\Tests\TestCase;

class ImmediateTest extends TestCase
{
    protected $manager;
    
    public function setUp()
    {
        $this->manager = $this->getMock(ImmediateManager::class);
    }
    
    public function createImmediate(callable $callback, array $args = [])
    {
        return new Immediate($this->manager, $callback, $args);
    }

    public function testCall()
    {
        $immediate = $this->createImmediate($this->createCallback(2));
        
        $immediate->call();
        $immediate->call();
    }
    
    /**
     * @depends testCall
     */
    public function testInvoke()
    {
        $immediate = $this->createImmediate($this->createCallback(2));
        
        $immediate();
        $immediate();
    }
    
    public function testIsPending()
    {
        $immediate = $this->createImmediate($this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('isPending')
            ->with($this->identicalTo($immediate))
            ->will($this->returnValue(true));
        
        $this->assertTrue($immediate->isPending());
    }
    
    public function testExecute()
    {
        $immediate = $this->createImmediate($this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($immediate));

        $immediate->execute();
    }

    public function testCancel()
    {
        $immediate = $this->createImmediate($this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('cancel')
            ->with($this->identicalTo($immediate));

        $immediate->cancel();
    }

    /**
     * @depends testCall
     */
    public function testSetCallback()
    {
        $timer = $this->createImmediate($this->createCallback(0));

        $value = 1;

        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->with($this->identicalTo($value));

        $timer->setCallback($callback, $value);

        $timer->call();
    }

    public function testUnreference()
    {
        $immediate = $this->createImmediate($this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('unreference')
            ->with($this->identicalTo($immediate));

        $immediate->unreference();
    }

    public function testReference()
    {
        $immediate = $this->createImmediate($this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('reference')
            ->with($this->identicalTo($immediate));

        $immediate->reference();
    }

    /**
     * @depends testUnreference
     * @depends testExecute
     */
    public function testExecuteAfterUnreference()
    {
        $immediate = $this->createImmediate($this->createCallback(0));

        $this->manager->expects($this->exactly(2))
            ->method('unreference')
            ->with($this->identicalTo($immediate));

        $this->manager->expects($this->once())
            ->method('execute')
            ->with($this->identicalTo($immediate));

        $immediate->unreference();

        $immediate->execute();
    }

    /**
     * @depends testCall
     */
    public function testArguments()
    {
        $arg1 = 1;
        $arg2 = 2;
        $arg3 = 3;
        $arg4 = 4;
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with(
                     $this->identicalTo($arg1),
                     $this->identicalTo($arg2),
                     $this->identicalTo($arg3),
                     $this->identicalTo($arg4)
                 );
        
        $immediate = $this->createImmediate($callback, [$arg1, $arg2, $arg3, $arg4]);
        
        $immediate->call();
    }
}
