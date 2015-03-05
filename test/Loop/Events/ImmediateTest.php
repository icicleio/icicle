<?php
namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\Immediate;
use Icicle\Tests\TestCase;

class ImmediateTest extends TestCase
{
    protected $loop;
    
    public function setUp()
    {
        $this->loop = $this->createLoop();
    }
    
    protected function createLoop()
    {
        $loop = $this->getMockBuilder('Icicle\Loop\LoopInterface')
                     ->getMock();
        
        $loop->method('createImmediate')
             ->will($this->returnCallback(function (callable $callback, array $args = null) use ($loop) {
                 return new Immediate($loop, $callback, $args);
             }));
        
        return $loop;
    }
    
    public function testGetCallback()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(1));
        
        $callback = $immediate->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback();
    }
    
    public function testCall()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(2));
        
        $immediate->call();
        $immediate->call();
    }
    
    /**
     * @depends testCall
     */
    public function testInvoke()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(2));
        
        $immediate();
        $immediate();
    }
    
    public function testIsPending()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('isImmediatePending')
                   ->with($this->identicalTo($immediate))
                   ->will($this->returnValue(true));
        
        $this->assertTrue($immediate->isPending());
    }
    
    public function testCancel()
    {
        $immediate = $this->loop->createImmediate($this->createCallback(0));
        
        $this->loop->expects($this->once())
                   ->method('cancelImmediate')
                   ->with($this->identicalTo($immediate));
        
        $immediate->cancel();
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
        
        $immediate = $this->loop->createImmediate($callback, [$arg1, $arg2, $arg3, $arg4]);
        
        $immediate->call();
    }
}
