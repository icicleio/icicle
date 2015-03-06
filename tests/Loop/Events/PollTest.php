<?php
namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\Poll;
use Icicle\Tests\TestCase;

class PollTest extends TestCase
{
    const TIMEOUT = 0.1;
    
    protected $loop;
    
    public function setUp()
    {
        $this->loop = $this->createLoop();
    }
    
    protected function createLoop()
    {
        $loop = $this->getMockBuilder('Icicle\Loop\LoopInterface')
                     ->getMock();
        
        $loop->method('createPoll')
             ->will($this->returnCallback(function ($resource, callable $callback) use ($loop) {
                 return new Poll($loop, $resource, $callback);
             }));
        
        return $loop;
    }
    
    public function createSockets()
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }
    
    public function testGetResource()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->assertSame($socket, $poll->getResource());
    }
    
    /**
     * @depends testGetResource
     * @expectedException Icicle\Loop\Exception\InvalidArgumentException
     */
    public function testInvalidResource()
    {
        $poll = $this->loop->createPoll(1, $this->createCallbacK(0));
    }
    
    public function testGetCallback()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $poll = $this->loop->createPoll($socket, $callback);
        
        $callback = $poll->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback($socket, false);
    }
    
    public function testCall()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(2);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $poll = $this->loop->createPoll($socket, $callback);
        
        $poll->call($socket, false);
        $poll->call($socket, false);
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
        
        $poll = $this->loop->createPoll($socket, $callback);
        
        $poll($socket, false);
        $poll($socket, false);
    }
    
    /**
     * @depends testGetCallback
     */
    public function testSetCallback()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));
        
        $poll->setCallback($callback);
        
        $callback = $poll->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback($socket, false);
    }
    
    public function testListen()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('listenPoll')
                   ->with($this->identicalTo($poll));
        
        $poll->listen();
    }
    
    /**
     * @depends testListen
     */
    public function testListenWithTimeout()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('listenPoll')
                   ->with($this->identicalTo($poll), $this->identicalTo(self::TIMEOUT));
        
        $poll->listen(self::TIMEOUT);
    }
    
    public function testIsPending()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('isPollPending')
                   ->with($this->identicalTo($poll))
                   ->will($this->returnValue(true));
        
        $this->assertTrue($poll->isPending());
    }
    
    public function testFree()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('freePoll')
                   ->with($this->identicalTo($poll))
                   ->will($this->returnValue(true));
        
        $this->loop->expects($this->once())
                   ->method('isPollFreed')
                   ->with($this->identicalTo($poll))
                   ->will($this->returnValue(true));
        
        $poll->free();
        
        $this->assertTrue($poll->isFreed());
    }
    
    public function testCancel()
    {
        list($socket) = $this->createSockets();
        
        $poll = $this->loop->createPoll($socket, $this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('cancelPoll')
                   ->with($this->identicalTo($poll));
        
        $poll->cancel();
    }
}
