<?php
namespace Icicle\Tests\Stream\Structures;

use Icicle\Stream\Structures\Buffer;
use Icicle\Tests\TestCase;

class BufferIteratorTest extends TestCase
{
    const INITIAL_STRING = 'abcdefghijklmnopqrstuvwxyz';
    
    const APPEND_STRING = '1234567890';
    
    protected $buffer;
    
    protected $iterator;
    
    public function setUp()
    {
        $this->buffer = new Buffer(self::INITIAL_STRING);
        $this->iterator = $this->buffer->getIterator();
    }
    
    public function testIteration()
    {
        $result = null;
        
        for ($i = 0, $this->iterator->rewind(); $this->iterator->valid(); ++$i, $this->iterator->next()) {
            if ($i !== $this->iterator->key()) {
                $this->fail('Got invalid key from iterator.');
            }
            
            $result .= $this->iterator->current();
        }
        
        $this->assertSame(self::INITIAL_STRING, $result);
        
        $this->iterator->prev();
        
        $this->assertTrue($this->iterator->valid());
        $this->assertSame($this->buffer->getLength() - 1, $this->iterator->key());
    }
    
    public function testSeek()
    {
        $this->iterator->seek($this->buffer->getLength() - 1);
        
        $this->assertSame($this->buffer->getLength() - 1, $this->iterator->key());
        $this->assertSame(substr($this->buffer, -1), $this->iterator->current());
    }
    
    public function testSeekWithInvalidPosition()
    {
        $this->iterator->seek(-1);
        
        $this->assertSame(0, $this->iterator->key());
    }
    
    public function testInsert()
    {
        $this->iterator->next();

        $this->iterator->insert(self::APPEND_STRING);
        
        $this->assertSame(substr(self::INITIAL_STRING, 0, 1) . self::APPEND_STRING . substr(self::INITIAL_STRING, 1), (string) $this->buffer);
    }
    
    /**
     * @depends testInsert
     * @expectedException \Icicle\Stream\Exception\LogicException
     */
    public function testInsertOnInvalidIterator()
    {
        for ($this->iterator->rewind(); $this->iterator->valid(); $this->iterator->next());
        
        $this->iterator->insert(self::APPEND_STRING);
    }
    
    public function testReplace()
    {
        $this->iterator->replace(self::APPEND_STRING);
        
        $this->assertSame(self::APPEND_STRING . substr(self::INITIAL_STRING, 1), (string) $this->buffer);
    }
    
    /**
     * @depends testReplace
     * @expectedException \Icicle\Stream\Exception\LogicException
     */
    public function testReplaceOnInvalidIterator()
    {
        for ($this->iterator->rewind(); $this->iterator->valid(); $this->iterator->next());
        
        $this->iterator->replace(self::APPEND_STRING);
    }
    
    public function testRemove()
    {
        $this->iterator->remove();
        
        $this->assertSame(substr(self::INITIAL_STRING, 1), (string) $this->buffer);
        
        $this->iterator->next();
        
        $this->iterator->remove();
        
        $this->assertSame(substr(self::INITIAL_STRING, 2), (string) $this->buffer);
    }
    
    /**
     * @depends testRemove
     * @expectedException \Icicle\Stream\Exception\LogicException
     */
    public function testRemoveOnInvalidIterator()
    {
        for ($this->iterator->rewind(); $this->iterator->valid(); $this->iterator->next());
        
        $this->iterator->remove();
    }
}