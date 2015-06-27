<?php
namespace Icicle\Tests\Stream\Structures;

use Icicle\Stream\Structures\Buffer;
use Icicle\Stream\Structures\BufferIterator;
use Icicle\Tests\TestCase;

class BufferTest extends TestCase
{
    const INITIAL_STRING = 'abcdefghijklmnopqrstuvwxyz';
    
    const APPEND_STRING = '1234567890';
    
    protected $buffer;
    
    public function setUp()
    {
        $this->buffer = new Buffer(self::INITIAL_STRING);
    }
    
    public function testGetLength()
    {
        $this->assertSame(strlen(self::INITIAL_STRING), $this->buffer->getLength()); 
    }
    
    public function testCount()
    {
        $this->assertSame(strlen(self::INITIAL_STRING), count($this->buffer)); 
    }
    
    public function testIsEmpty()
    {
        $this->assertFalse($this->buffer->isEmpty());
        
        $buffer = new Buffer();
        $this->assertTrue($buffer->isEmpty());
    }
    
    public function testToString()
    {
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testPush()
    {
        $this->buffer->push(self::APPEND_STRING);
        
        $this->assertSame(self::INITIAL_STRING . self::APPEND_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testUnshift()
    {
        $this->buffer->unshift(self::APPEND_STRING);
        
        $this->assertSame(self::APPEND_STRING . self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testRemove()
    {
        $length = 10;
        
        $result = $this->buffer->remove($length);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, $length), $result);
        $this->assertSame(substr(self::INITIAL_STRING, $length), (string) $this->buffer);
    }
    
    /**
     * @depends testRemove
     */
    public function testRemoveWithOffset()
    {
        $length = 10;
        $offset = 5;
        
        $result = $this->buffer->remove($length, $offset);
        
        $this->assertSame(substr(self::INITIAL_STRING, $offset, $length), $result);
        $this->assertSame(substr(self::INITIAL_STRING, 0, $offset) . substr(self::INITIAL_STRING, $offset + $length), (string) $this->buffer);
    }
    
    /**
     * @depends testRemove
     */
    public function testRemoveWithInvalidLength()
    {
        $length = -1;
        
        $result = $this->buffer->remove($length);
        
        $this->assertSame('', $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testRemove
     */
    public function testRemoveWithInvalidOffset()
    {
        $length = 10;
        $offset = -1;
        
        $result = $this->buffer->remove($length, $offset);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, $length), $result);
        $this->assertSame(substr(self::INITIAL_STRING, $length), (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testShift()
    {
        $length = 10;
        
        $result = $this->buffer->shift($length);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, $length), $result);
        $this->assertSame(substr(self::INITIAL_STRING, $length), (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testPeek()
    {
        $length = 10;
        
        $result = $this->buffer->peek($length);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, $length), $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testPeek
     */
    public function testPeekWithOffset()
    {
        $length = 10;
        $offset = 5;
        
        $result = $this->buffer->peek($length, $offset);
        
        $this->assertSame(substr(self::INITIAL_STRING, $offset, $length), $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testPeek
     */
    public function testPeekWithInvalidLength()
    {
        $length = -1;
        
        $result = $this->buffer->peek($length);
        
        $this->assertSame('', $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testPeek
     */
    public function testPeekWithInvalidOffset()
    {
        $length = 10;
        $offset = -1;
        
        $result = $this->buffer->peek($length, $offset);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, $length), $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testPeek
     */
    public function testPeekWithLengthGreaterThanBufferLength()
    {
        $length = 100;
        
        $result = $this->buffer->peek($length);
        
        $this->assertSame(self::INITIAL_STRING, $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testDrain()
    {
        $result = $this->buffer->drain();
        
        $this->assertSame(self::INITIAL_STRING, $result);
        $this->assertSame('', (string) $this->buffer);
        $this->assertTrue($this->buffer->isEmpty());
    }
    
    /**
     * @depends testToString
     */
    public function testPop()
    {
        $length = 10;
        
        $result = $this->buffer->pop($length);
        
        $this->assertSame(substr(self::INITIAL_STRING, -$length), $result);
        $this->assertSame(substr(self::INITIAL_STRING, 0, -$length), (string) $this->buffer);
    }
    
    /**
     * @depends testPop
     */
    public function testPopWithInvalidLength()
    {
        $length = -1;
        
        $result = $this->buffer->pop($length);
        
        $this->assertSame('', $result);
        $this->assertSame(self::INITIAL_STRING, (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testInsert()
    {
        $position = 10;
        
        $this->buffer->insert(self::APPEND_STRING, $position);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, $position) . self::APPEND_STRING . substr(self::INITIAL_STRING, $position), (string) $this->buffer);
    }
    
    /**
     * @depends testToString
     */
    public function testReplace()
    {
        $search = substr(self::INITIAL_STRING, 3, 3);
        
        $this->assertSame(1, $this->buffer->replace($search, self::APPEND_STRING));
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, 3) . self::APPEND_STRING . substr(self::INITIAL_STRING, 6), (string) $this->buffer);
    }
    
    public function testSearch()
    {
        $search = substr(self::INITIAL_STRING, 3, 3);
        
        $this->assertSame(3, $this->buffer->search($search));
        
        $this->assertFalse($this->buffer->search(self::APPEND_STRING));
    }
    
    /**
     * @depends testSearch
     * @depends testPush
     */
    public function testReverseSearch()
    {
        $search = substr(self::INITIAL_STRING, 0, 3);
        
        $this->buffer->push(self::INITIAL_STRING);
        
        $this->assertSame(strlen(self::INITIAL_STRING), $this->buffer->search($search, true));
    }
    
    public function testOffsetExists()
    {
        $this->assertTrue($this->buffer->offsetExists(0));
        $this->assertTrue($this->buffer->offsetExists(strlen(self::INITIAL_STRING) - 1));
        $this->assertFalse($this->buffer->offsetExists(strlen(self::INITIAL_STRING)));
        $this->assertFalse($this->buffer->offsetExists(-1));
    }
    
    public function testOffsetGet()
    {
        $this->assertSame(substr(self::INITIAL_STRING, 0, 1), $this->buffer->offsetGet(0));
        $this->assertSame(substr(self::INITIAL_STRING, -1, 1), $this->buffer->offsetGet(strlen(self::INITIAL_STRING) - 1));
    }
    
    public function testOffsetSet()
    {
        $this->buffer->offsetSet(0, self::APPEND_STRING);
        $this->assertSame(self::APPEND_STRING . substr(self::INITIAL_STRING, 1), (string) $this->buffer);
    }
    
    public function testOffsetUnset()
    {
        $this->buffer->offsetUnset(0, self::APPEND_STRING);
        $this->assertSame(substr(self::INITIAL_STRING, 1), (string) $this->buffer);
    }
    
    public function testGetInterator()
    {
        $iterator = $this->buffer->getIterator();
        
        $this->assertInstanceOf(BufferIterator::class, $iterator);
    }
}