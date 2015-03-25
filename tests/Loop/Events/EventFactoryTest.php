<?php
namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\EventFactory;
use Icicle\Tests\TestCase;

class EventFactoryTest extends TestCase
{
    protected $factory;
    
    public function setUp()
    {
        $this->factory = new EventFactory();
    }
    
    public function createSockets()
    {
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }
    
    public function testCreateSocketEvent()
    {
        list($socket) = $this->createSockets();
        
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo($socket), $this->identicalTo(false));

        $manager = $this->getMock('Icicle\Loop\Manager\SocketManagerInterface');

        $poll = $this->factory->socket($manager, $socket, $callback);
        
        $this->assertInstanceOf('Icicle\Loop\Events\SocketEventInterface', $poll);
        
        $this->assertSame($socket, $poll->getResource());
        
        $callback = $poll->getCallback();
        $callback($socket, false);
    }
    
    public function testCreateTimer()
    {
        $timeout = 0.1;
        $periodic = true;

        $manager = $this->getMock('Icicle\Loop\Manager\TimerManagerInterface');

        $timer = $this->factory->timer($manager, $this->createCallback(1), $timeout, $periodic);
        
        $this->assertInstanceOf('Icicle\Loop\Events\TimerInterface', $timer);
        
        $this->assertSame($timeout, $timer->getInterval());
        $this->assertSame($periodic, $timer->isPeriodic());
        
        $callback = $timer->getCallback();
        $callback();
    }
    
    public function testCreateImmediate()
    {
        $manager = $this->getMock('Icicle\Loop\Manager\ImmediateManagerInterface');

        $immediate = $this->factory->immediate($manager, $this->createCallback(1));
        
        $this->assertInstanceOf('Icicle\Loop\Events\ImmediateInterface', $immediate);
        
        $callback = $immediate->getCallback();
        $callback();
    }
}
