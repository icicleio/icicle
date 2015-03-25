<?php
namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\Await;
use Icicle\Tests\TestCase;

class AwaitTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $loop;

    protected $manager;
    
    public function setUp()
    {
        $this->loop = $this->getMock('Icicle\Loop\LoopInterface');

        $this->manager = $this->getMock('Icicle\Loop\Manager\AwaitManagerInterface');

        $this->loop->method('createAwait')
            ->will($this->returnCallback(function ($resource, callable $callback) {
                return new Await($this->manager, $resource, $callback);
            }));
    }
    
    public function createSockets()
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }
    
    public function testGetResource()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->assertSame($socket, $await->getResource());
    }
    
    /**
     * @depends testGetResource
     * @expectedException Icicle\Loop\Exception\InvalidArgumentException
     */
    public function testInvalidResource()
    {
        $await = $this->loop->createAwait(1, $this->createCallbacK(0));
    }
    
    public function testGetCallback()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $await = $this->loop->createAwait($socket, $callback);
        
        $callback = $await->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback($socket, false);
    }
    
    public function testCall()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $await = $this->loop->createAwait($socket, $callback);
        
        $await->call($socket, false);
        $await->call($socket, false);
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
        
        $await = $this->loop->createAwait($socket, $callback);
        
        $await($socket, false);
        $await($socket, false);
    }
    
    /**
     * @depends testGetCallback
     */
    public function testSetCallback()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $await->setCallback($callback);
        
        $callback = $await->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback($socket, false);
    }
    
    public function testListen()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));

        $this->manager->expects($this->once())
            ->method('listen')
            ->with($this->identicalTo($await));
        
        $await->listen();
    }
    
    /**
     * @depends testListen
     */
    public function testListenWithTimeout()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('listen')
            ->with($this->identicalTo($await), $this->identicalTo(self::TIMEOUT));
        
        $await->listen(self::TIMEOUT);
    }
    
    public function testIsPending()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('isPending')
            ->with($this->identicalTo($await))
            ->will($this->returnValue(true));
        
        $this->assertTrue($await->isPending());
    }
    
    public function testFree()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('free')
            ->with($this->identicalTo($await))
            ->will($this->returnValue(true));
        
        $this->manager->expects($this->once())
            ->method('isFreed')
            ->with($this->identicalTo($await))
            ->will($this->returnValue(true));
        
        $await->free();
        
        $this->assertTrue($await->isFreed());
    }
    
    public function testCancel()
    {
        list($socket) = $this->createSockets();
        
        $await = $this->loop->createAwait($socket, $this->createCallback(0));
        
        $this->manager->expects($this->once())
            ->method('cancel')
            ->with($this->identicalTo($await));
        
        $await->cancel();
    }
}
