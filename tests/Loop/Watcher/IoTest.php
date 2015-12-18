<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Tests\Loop\Watcher;

use Icicle\Loop\{Manager\IoManager, Watcher\Io};
use Icicle\Tests\TestCase;

class IoTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $manager;
    
    public function setUp()
    {
        $this->manager = $this->getMock(IoManager::class);
    }
    
    public function createIo($resource, callable $callback, $data = null)
    {
        return new Io($this->manager, $resource, $callback, false, $data);
    }
    
    public function createSockets()
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }
    
    public function testGetResource()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createIo($socket, $this->createCallback(0));
        
        $this->assertSame($socket, $event->getResource());
    }

    public function testCall()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false), $this->isInstanceOf(Io::class));
        
        $event = $this->createIo($socket, $callback);
        
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
        
        $event = $this->createIo($socket, $callback);
        
        $event(false);
        $event(false);
    }
    
    public function testListen()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createIo($socket, $this->createCallback(0));
        
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

        $event = $this->createIo($socket, $this->createCallback(0));

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
        
        $event = $this->createIo($socket, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('listen')
            ->with($this->identicalTo($event), $this->identicalTo(self::TIMEOUT));
        
        $event->listen(self::TIMEOUT);
    }
    
    public function testIsPending()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createIo($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('isPending')
            ->with($this->identicalTo($event))
            ->will($this->returnValue(true));
        
        $this->assertTrue($event->isPending());
    }
    
    public function testFree()
    {
        list($socket) = $this->createSockets();
        
        $event = $this->createIo($socket, $this->createCallback(0));
        
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
        
        $event = $this->createIo($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('cancel')
            ->with($this->identicalTo($event));
        
        $event->cancel();
    }

    public function testUnreference()
    {
        list($socket) = $this->createSockets();

        $event = $this->createIo($socket, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('unreference')
            ->with($this->identicalTo($event));

        $event->unreference();
    }

    public function testReference()
    {
        list($socket) = $this->createSockets();

        $event = $this->createIo($socket, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('reference')
            ->with($this->identicalTo($event));

        $event->reference();
    }

    public function testData()
    {
        list($socket) = $this->createSockets();

        $data = 'data';

        $event = $this->createIo($socket, $this->createCallback(0), $data);

        $this->assertSame($data, $event->getData());

        $event->setData($data = 'test');

        $this->assertSame($data, $event->getData());
    }
}
