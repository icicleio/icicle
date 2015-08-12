<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\SocketEvent;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Tests\TestCase;

class SocketEventTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $manager;
    
    public function setUp()
    {
        $this->manager = $this->getMock(SocketManagerInterface::class);
    }
    
    public function createSocketEvent($resource, callable $callback)
    {
        return new SocketEvent($this->manager, $resource, $callback);
    }
    
    public function createSockets()
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }
    
    public function testGetResource()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createSocketEvent($socket, $this->createCallback(0));
        
        $this->assertSame($socket, $event->getResource());
    }
    
    /**
     * @depends testGetResource
     * @expectedException \Icicle\Loop\Exception\NonResourceError
     */
    public function testInvalidResource()
    {
        $event = $this->createSocketEvent(1, $this->createCallbacK(0));
    }
    
    public function testCall()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $event = $this->createSocketEvent($socket, $callback);
        
        $event->call(false);
        $event->call(false);
    }
    
    /**
     * @depends testCall
     */
    public function testInvoke()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $event = $this->createSocketEvent($socket, $callback);
        
        $event(false);
        $event(false);
    }
    
    public function testListen()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createSocketEvent($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('listen')
            ->with($this->identicalTo($event));
        
        $event->listen();
    }

    /**
     * @depends testCall
     */
    public function testSetCallback()
    {
        list($socket) = $this->createSockets();

        $callback = $this->createCallback(2);
        $callback->method('__invoke')
            ->with($this->identicalTo($socket), $this->identicalTo(false));

        $event = $this->createSocketEvent($socket, $this->createCallback(0));

        $event->setCallback($callback);

        $event->call(false);
        $event->call(false);
    }
    
    /**
     * @depends testListen
     */
    public function testListenWithTimeout()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createSocketEvent($socket, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('listen')
            ->with($this->identicalTo($event), $this->identicalTo(self::TIMEOUT));
        
        $event->listen(self::TIMEOUT);
    }
    
    public function testIsPending()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createSocketEvent($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('isPending')
            ->with($this->identicalTo($event))
            ->will($this->returnValue(true));
        
        $this->assertTrue($event->isPending());
    }
    
    public function testFree()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createSocketEvent($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('free')
            ->with($this->identicalTo($event))
            ->will($this->returnValue(true));
        
        $this->manager->expects($this->once())
            ->method('isFreed')
            ->with($this->identicalTo($event))
            ->will($this->returnValue(true));
        
        $event->free();
        
        $this->assertTrue($event->isFreed());
    }
    
    public function testCancel()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createSocketEvent($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('cancel')
            ->with($this->identicalTo($event));
        
        $event->cancel();
    }
}
