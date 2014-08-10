<?php
namespace Icicle\Tests\Event;

use Icicle\Event\Exception\InvalidEventException;
use Icicle\Tests\Stub\EventEmitterStub;
use Icicle\Tests\Stub\ListenerStub;
use Icicle\Tests\TestCase;

class EventEmitterTest extends TestCase
{
    private $emitter;
    
    public function setUp()
    {
        $this->emitter = new EventEmitterStub();
        $this->emitter->createEvent('event1');
        $this->emitter->createEvent('event2');
    }
    
    public function testAddListenerWithClosure()
    {
        $callback = function () {};
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
    }
    
    public function testAddListenerWithMethod()
    {
        $listener = new ListenerStub();
        
        $callback = [$listener, 'method'];
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
    }
    
    public function testAddListenerWithStaticMethod()
    {
        $callback = ['Icicle\Tests\Stub\ListenerStub', 'staticMethod'];
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
        
        $this->emitter->addListener('event1', 'Icicle\Tests\Stub\ListenerStub::staticMethod');
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
    }
    
    public function testAddListenerWithFunction()
    {
        $callback = 'strlen';
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertSame(1, $this->emitter->getListenerCount('event1'));
    }
    
    /**
     * @expectedException Icicle\Event\Exception\InvalidEventException
     */
    public function testAddListenerToInvalidEvent()
    {
        $this->emitter->addListener('invalid', $this->createCallback(0));
    }
    
    public function testEmitWithNoListeners()
    {
        $this->assertFalse($this->emitter->emit('event1'));
    }
    
    /**
     * @depends testAddListenerWithClosure
     */
    public function testEmitWithNoArguments()
    {
        $callback = $this->createCallback(1);
        
        $this->emitter->addListener('event1', $callback);
        
        $this->assertTrue($this->emitter->emit('event1'));
    }
    
    /**
     * @depends testAddListenerWithClosure
     */
    public function testEmit()
    {
        $this->emitter->addListener('event1', $this->createCallback(1));
        
        $this->assertTrue($this->emitter->emit('event1'));
    }
    
    /**
     * @depends testEmit
     */
    public function testEmitWithArguments()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1, 2, 3));
        
        $this->emitter->addListener('event1', $callback);
        
        $this->emitter->emit('event1', 1, 2, 3);
    }
    
    /**
     * @expectedException Icicle\Event\Exception\InvalidEventException
     */
    public function testEmitInvalidEvent()
    {
        $this->emitter->emit('invalid');
    }
    
    /**
     * @depends testEmit
     */
    public function testOn()
    {
        $callback = $this->createCallback(2);
        
        $this->emitter->on('event1', $callback);
        
        $this->emitter->emit('event1');
        $this->emitter->emit('event1');
    }
    
    /**
     * @depends testEmitWithArguments
     */
    public function testOnce()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1, 2, 3));
        
        $this->emitter->once('event1', $callback);
        
        $this->emitter->emit('event1', 1, 2, 3);
        $this->emitter->emit('event1', 1, 2, 3);
    }
    
    /**
     * @depends testEmit
     */
    public function testRemoveListener()
    {
        $callback = $this->createCallback(0);
        
        $this->emitter->addListener('event1', $callback);
        
        $this->emitter->removeListener('event1', $callback);
        
        $this->assertSame(0, $this->emitter->getListenerCount('event1'));
        
        $this->emitter->emit('event1');
    }
    
    /**
     * @depends testOnce
     */
    public function testRemoveOnceListener()
    {
        $callback = $this->createCallback(0);
        
        $this->emitter->once('event1', $callback);
        
        $this->emitter->removeListener('event1', $callback);
        
        $this->emitter->emit('event1');
    }
    
    /**
     * @expectedException Icicle\Event\Exception\InvalidEventException
     */
    public function testRemoveListenerWithInvalidEvent()
    {
        $this->emitter->removeListener('invalid', $this->createCallback(0));
    }
    
    /**
     * @depends testEmit
     */
    public function testOff()
    {
        $callback = $this->createCallback(0);
        
        $this->emitter->on('event1', $callback);
        
        $this->emitter->off('event1', $callback);
        
        $this->emitter->emit('event1');
    }
    
    /**
     * @depends testEmit
     */
    public function testRemoveAllListeners()
    {
        $this->emitter->addListener('event1', $this->createCallback(0));
        $this->emitter->addListener('event1', $this->createCallback(0));
        $this->emitter->addListener('event2', $this->createCallback(0));
        
        $this->emitter->removeAllListeners();
        
        $this->emitter->emit('event1');
        $this->emitter->emit('event2');
    }
    
    /**
     * @depends testEmit
     */
    public function testRemoveAllListenersWithEvent()
    {
        $this->emitter->addListener('event1', $this->createCallback(0));
        $this->emitter->addListener('event1', $this->createCallback(0));
        $this->emitter->addListener('event2', $this->createCallback(1));
        
        $this->emitter->removeAllListeners('event1');
        
        $this->emitter->emit('event1');
        $this->emitter->emit('event2');
    }
    
    /**
     * @expectedException Icicle\Event\Exception\InvalidEventException
     */
    public function testRemoveAllListenersWithInvalidEvent()
    {
        $this->emitter->removeAllListeners('invalid');
    }
    
    /**
     * @depends testAddListenerWithClosure
     */
    public function testGetListeners()
    {
        $this->emitter->addListener('event1', $this->createCallback(0));
        $this->emitter->addListener('event1', $this->createCallback(0));
        
        $listeners = $this->emitter->getListeners('event1');
        
        $this->assertTrue(is_array($listeners));
        $this->assertSame(2, count($listeners));
    }
    
    /**
     * @expectedException Icicle\Event\Exception\InvalidEventException
     */
    public function testGetListenersWithInvalidEvent()
    {
        $listeners = $this->emitter->getListeners('invalid');
    }
    
    /**
     * @expectedException Icicle\Event\Exception\InvalidEventException
     */
    public function testGetListenerCountWithInvalidEvent()
    {
        $this->emitter->getListenerCount('invalid');
    }
    
    /**
     * @depends testRemoveListenerWithInvalidEvent
     */
    public function testInvalidEventExceptionGetEvent()
    {
        try {
            $this->emitter->removeListener('invalid', $this->createCallback(0));
        } catch (InvalidEventException $e) {
            $this->assertSame('invalid', $e->getEvent());
        }
    }
}
