<?php
namespace Icicle\Stream\Structures;

use Icicle\Stream\Exception\LogicException;

/**
 */
class BufferIterator implements \SeekableIterator
{
    /**
     * @var Buffer
     */
    private $buffer;
    
    /**
     * @var int
     */
    private $current = 0;

    /**
     * @param \Icicle\Stream\Structures\Buffer $buffer
     */
    public function __construct(Buffer $buffer)
    {
        $this->buffer = $buffer;
    }
    
    /**
     * Rewinds the iterator to the beginning of the buffer.
     */
    public function rewind()
    {
        $this->current = 0;
    }
    
    /**
     * Determines if the iterator is valid.
     *
     * @return bool
     */
    public function valid()
    {
        return isset($this->buffer[$this->current]);
    }
    
    /**
     * Returns the current position (key) of the iterator.
     *
     * @return int
     */
    public function key()
    {
        return $this->current;
    }
    
    /**
     * Returns the current character in the buffer at the iterator position.
     *
     * @return string
     */
    public function current()
    {
        return $this->buffer[$this->current];
    }
    
    /**
     * Moves to the next character in the buffer.
     */
    public function next()
    {
        ++$this->current;
    }
    
    /**
     * Moves to the previous character in the buffer.
     */
    public function prev()
    {
        --$this->current;
    }
    
    /**
     * Moves to the given position in the buffer.
     *
     * @param int $position
     */
    public function seek($position)
    {
        $position = (int) $position;
        if (0 > $position) {
            $position = 0;
        }
        
        $this->current = $position;
    }
    
    /**
     * Inserts the given string into the buffer at the current iterator position.
     *
     * @param string $data
     *
     * @throws \Icicle\Stream\Exception\LogicException If the iterator is invalid.
     */
    public function insert($data)
    {
        if (!$this->valid()) {
            throw new LogicException('The iterator is not valid!');
        }
        
        $this->buffer[$this->current] = $data . $this->buffer[$this->current];
    }
    
    /**
     * Replaces the byte at the current iterator position with the given string.
     *
     * @param string $data
     *
     * @return string
     *
     * @throws \Icicle\Stream\Exception\LogicException If the iterator is invalid.
     */
    public function replace($data)
    {
        if (!$this->valid()) {
            throw new LogicException('The iterator is not valid!');
        }
        
        $temp = $this->buffer[$this->current];
        
        $this->buffer[$this->current] = $data;
        
        return $temp;
    }
    
    /**
     * Removes the byte at the current iterator position and moves the iterator to the previous character.
     *
     * @return string
     *
     * @throws \Icicle\Stream\Exception\LogicException If the iterator is invalid.
     */
    public function remove()
    {
        if (!$this->valid()) {
            throw new LogicException('The iterator is not valid!');
        }
        
        $temp = $this->buffer[$this->current];
        
        unset($this->buffer[$this->current]);
        
        --$this->current;

        return $temp;
    }
}
