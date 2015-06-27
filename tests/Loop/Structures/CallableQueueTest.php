<?php
namespace Icicle\Tests\Loop\Structures;

use Icicle\Loop\Structures\CallableQueue;
use Icicle\Tests\TestCase;

class CallableQueueTest extends TestCase
{
    private $queue;
    
    public function setUp()
    {
        $this->queue = new CallableQueue();
    }
    
    public function testInsert()
    {
        $this->queue->insert($this->createCallback(1));
        
        $this->assertFalse($this->queue->isEmpty());
        $this->assertSame(1, $this->queue->count());
        
        $this->assertSame(1, $this->queue->call());
    }
    
    /**
     * @depends testInsert
     */
    public function testInsertWithArguments()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
                 ->with($this->identicalTo(1, 2, 3.14, 'test'));
        
        $this->queue->insert($callback, [1, 2, 3.14, 'test']);
        
        $this->queue->call();
    }
    
    /**
     * @depends testInsert
     */
    public function testMultipleInsert()
    {
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(1));
        
        $this->assertSame(3, $this->queue->call());
    }
    
    /**
     * @depends testInsert
     */
    public function testClear()
    {
        $this->queue->insert($this->createCallback(0));
        
        $this->queue->clear();
        
        $this->assertSame(0, $this->queue->call());
    }
    
    /**
     * @depends testInsert
     */
    public function testCall()
    {
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(1));
        
        $this->assertSame(3, $this->queue->call());
    }
    
    /**
     * @depends testCall
     */
    public function testInvoke()
    {
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(1));
        
        $queue = $this->queue;
        $this->assertSame(2, $queue());
    }
    
    /**
     * @depends testCall
     */
    public function testMaxDepth()
    {
        $previous = $this->queue->maxDepth(1);
        
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(1));
        
        $this->queue->call();
        
        $this->assertSame(1, $previous = $this->queue->maxDepth(2));
        
        $this->queue->insert($this->createCallback(1));
        $this->queue->insert($this->createCallback(0));
        
        $this->queue->call();
    }

    /**
     * @depends testCall
     */
    public function testCallbackThrowingExceptionIsRemovedFromQueue()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->will($this->throwException(new \Exception()));

        $this->queue->insert($callback);

        $this->assertSame(1, $this->queue->count());

        try {
            $this->queue->call();
            $this->fail('Exception was not thrown from CallableQueue::call().');
        } catch (\Exception $e) {
            $this->assertSame(0, $this->queue->count());
        }
    }

    /**
     * @depends testCallbackThrowingExceptionIsRemovedFromQueue
     */
    public function testCallbackRemainsInQueueAfterCallbackThrowingException()
    {
        $callback = $this->createCallback(1);
        $callback->method('__invoke')
            ->will($this->throwException(new \Exception()));

        $this->queue->insert($callback);
        $this->queue->insert($this->createCallback(0));

        $this->assertSame(2, $this->queue->count());

        try {
            $this->queue->call();
            $this->fail('Exception was not thrown from CallableQueue::call().');
        } catch (\Exception $e) {
            $this->assertSame(1, $this->queue->count());
        }
    }
}
