<?php
namespace Icicle\Tests\Loop\Events;

use Icicle\Loop\Events\Immediate;
use Icicle\Tests\TestCase;

class ImmediateTest extends TestCase
{
    protected $manager;
    
    public function setUp()
    {
        $this->manager = $this->getMock('Icicle\Loop\Events\Manager\ImmediateManagerInterface');
    }
    
    public function createImmediate(callable $callback, array $args = null)
    {
        return new Immediate($this->manager, $callback, $args);
    }
    
    public function testGetCallback()
    {
        $immediate = $this->createImmediate($this->createCallback(1));
        
        $callback = $immediate->getCallback();
        
        $this->assertTrue(is_callable($callback));
        
        $callback();
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
