<?php
namespace Icicle\Tests\Loop\Structures;

use Icicle\Loop\Structures\UnreferencableObjectStorage;
use Icicle\Tests\TestCase;

class UnreferencableObjectStorageTest extends TestCase
{
    private $storage;
    
    public function setUp()
    {
        $this->storage = new UnreferencableObjectStorage();
    }
    
    public function createObject()
    {
        return (object) [];
    }
    
    public function testUnreference()
    {
        $object = $this->createObject();
        
        $this->storage->attach($object);
        $this->storage->unreference($object);
        
        $this->assertFalse($this->storage->referenced($object));
        $this->assertFalse($this->storage->isEmpty());
        $this->assertSame(0, $this->storage->count());
        $this->assertSame(1, $this->storage->total());
    }
    
    /**
     * @depends testUnreference
     */
    public function testReference()
    {
        $object = $this->createObject();
        
        $this->storage->attach($object);
        $this->storage->unreference($object);
        
        $this->storage->reference($object);
        
        $this->assertSame(1, $this->storage->count());
    }
    
    /**
     * @depends testUnreference
     */
    public function testUnreferenceOnUncontainedObject()
    {
        $object = $this->createObject();
        
        $this->storage->unreference($object);
        
        $this->assertTrue($this->storage->isEmpty());
        $this->assertSame(0, $this->storage->count());
    }
    
    /**
     * @depends testReference
     */
    public function testReferenceOnUncontainedObject()
    {
        $object = $this->createObject();
        
        $this->storage->reference($object);
        
        $this->assertTrue($this->storage->isEmpty());
        $this->assertSame(0, $this->storage->count());
    }
    
    /**
     * @depends testUnreference
     */
    public function testDetach()
    {
        $object = $this->createObject();
        
        $this->storage->attach($object);
        $this->storage->unreference($object);
        
        $this->storage->detach($object);
        
        $this->assertFalse($this->storage->contains($object));
        $this->assertFalse($this->storage->referenced($object));
        $this->assertTrue($this->storage->isEmpty());
        $this->assertSame(0, $this->storage->count());
    }
    
    /**
     * @depends testUnreference
     */
    public function testOffsetUnset()
    {
        $object = $this->createObject();
        
        $this->storage->attach($object);
        $this->storage->unreference($object);
        
        unset($this->storage[$object]);
        
        $this->assertFalse($this->storage->contains($object));
        $this->assertFalse($this->storage->referenced($object));
        $this->assertTrue($this->storage->isEmpty());
        $this->assertSame(0, $this->storage->count());
    }
    
    /**
     * @depends testUnreference
     */
    public function testAddAll()
    {
        $object1 = $this->createObject();
        $object2 = $this->createObject();
        $object3 = $this->createObject();
        
        $storage = new UnreferencableObjectStorage();
        
        $storage->attach($object1);
        $storage->unreference($object1);
        
        $storage->attach($object3);
        
        $this->storage->attach($object2);
        $this->storage->unreference($object2);
        
        $this->storage->addAll($storage);
        
        $this->assertTrue($this->storage->contains($object1));
        $this->assertTrue($this->storage->contains($object2));
        $this->assertTrue($this->storage->contains($object2));
        
        $this->assertFalse($this->storage->referenced($object1));
        $this->assertFalse($this->storage->referenced($object2));
        $this->assertTrue($this->storage->referenced($object3));
    }
    
    /**
     * @depends testUnreference
     */
    public function testRemoveAll()
    {
        $object1 = $this->createObject();
        $object2 = $this->createObject();
        $object3 = $this->createObject();
        
        $storage = new UnreferencableObjectStorage();
        
        $storage->attach($object1);
        $storage->unreference($object1);
        
        $this->storage->attach($object1);
        
        $this->storage->attach($object2);
        $this->storage->unreference($object2);
        
        $this->storage->attach($object3);
        
        $this->storage->removeAll($storage);
        
        $this->assertFalse($this->storage->contains($object1));
        $this->assertTrue($this->storage->contains($object2));
        $this->assertTrue($this->storage->contains($object2));
    }
    
    /**
     * @depends testUnreference
     */
    public function testRemoveAllExcept()
    {
        $object1 = $this->createObject();
        $object2 = $this->createObject();
        $object3 = $this->createObject();
        
        $storage = new UnreferencableObjectStorage();
        
        $storage->attach($object2);
        
        $storage->attach($object3);
        $storage->unreference($object3);
        
        $this->storage->attach($object1);
        
        $this->storage->attach($object2);
        $this->storage->unreference($object2);
        
        $this->storage->attach($object3);
        
        $this->storage->removeAllExcept($storage);
        
        $this->assertFalse($this->storage->contains($object1));
        $this->assertTrue($this->storage->contains($object2));
        $this->assertTrue($this->storage->contains($object3));
    }
}
