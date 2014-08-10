<?php
namespace Icicle\Tests\Timer;

use Icicle\Loop\Loop;
use Icicle\Tests\TestCase;
use Icicle\Timer\Immediate;

class ImmediateTest extends TestCase
{
    public function tearDown()
    {
        Loop::clear();
    }
    
    public function testEnqueue()
    {
        $immediate = Immediate::enqueue($this->createCallback(1));
        
        $this->assertInstanceOf('Icicle\Timer\Immediate', $immediate);
        
        $this->assertTrue(Loop::getInstance()->isImmediatePending($immediate));
        
        Loop::tick(false);
    }
    
    /**
     * @depends testEnqueue
     */
    public function testIsPending()
    {
        $immediate = Immediate::enqueue($this->createCallback(1));
        
        $this->assertTrue($immediate->isPending());
        
        Loop::tick(false);
        
        $this->assertFalse($immediate->isPending());
    }
    
    /**
     * @depends testEnqueue
     */
    public function testCancel()
    {
        $immediate = Immediate::enqueue($this->createCallback(0));
        
        $this->assertTrue($immediate->isPending());
        
        $immediate->cancel();
        
        $this->assertFalse($immediate->isPending());
        
        Loop::tick(false);
    }
    
    /**
     * @depends testEnqueue
     */
    public function testSet()
    {
        $immediate = Immediate::enqueue($this->createCallback(2));
        
        Loop::tick(false);
        
        $this->assertFalse($immediate->isPending());
        
        $immediate->set();
        
        $this->assertTrue($immediate->isPending());
        
        Loop::tick(false);
    }
    
    public function testInvoke()
    {
        $immediate = Immediate::enqueue($this->createCallback(2));
        
        Loop::tick(false);
        
        $this->assertFalse($immediate->isPending());
        
        $immediate();
        
        $this->assertTrue($immediate->isPending());
        
        Loop::tick(false);
    }
    
    /**
     * @depends testEnqueue
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
        
        $immediate = Immediate::enqueue($callback, $arg1, $arg2, $arg3, $arg4);
        
        Loop::tick(false);
    }
}
