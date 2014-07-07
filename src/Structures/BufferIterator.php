<?php
namespace Icicle\Structures;

use Icicle\Structures\Exception\InvalidArgumentException;
use Icicle\Structures\Exception\InvalidIteratorException;

/**
 */
class BufferIterator implements \Iterator
{
    /**
     * @var     Buffer
     */
    private $buffer;
    
    /**
     * @var     int
     */
    private $current = 0;
    
    /**
     * @var     bool
     */
    private $skip = false;
    
    /**
     * @param   Buffer $buffer
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
     * @return  bool
     */
    public function valid()
    {
        return isset($this->buffer[$this->current]);
    }
    
    /**
     * Returns the current position (key) of the iterator.
     *
     * @return  int
     */
    public function key()
    {
        return $this->current;
    }
    
    /**
     * Returns the current character in the buffer at the iterator position.
     *
     * @return  string
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
        if ($this->skip) {
            $this->skip = false;
        } else {
            ++$this->current;
        }
    }
    
    /**
     * Moves to the previous character in the buffer.
     */
    public function prev()
    {
        $this->skip = false;
        --$this->current;
    }
    
    /**
     * Moves to the given position in the buffer.
     *
     * @param   int $position
     */
    public function seek($position)
    {
        if (!is_int($position)) {
            throw new InvalidArgumentException('The position must be an integer.');
        }
        
        $this->current = $position;
    }
    
    /**
     * Inserts the given data into the buffer at the current iterator position.
     *
     * @param   string $data
     */
    public function insert($data)
    {
        if (!$this->valid()) {
            throw new InvalidIteratorException('The iterator is not valid!');
        }
        
        if (!is_string($data)) {
            throw new InvalidArgumentException('The data must be a string.');
        }
        
        $this->buffer[$this->current] = $this->buffer[$this->current] . $data;
    }
    
    /**
     * @param   string $data
     *
     * @return  string
     */
    public function replace($data)
    {
        if (!$this->valid()) {
            throw new InvalidIteratorException('The iterator is not valid!');
        }
        
        $temp = $this->buffer[$this->current];
        
        $this->buffer[$this->current] = $data;
        
        return $temp;
    }
    
    /**
     * @return  string
     */
    public function remove()
    {
        if (!$this->valid()) {
            throw new InvalidIteratorException('The iterator is not valid!');
        }
        
        $temp = $this->buffer[$this->current];
        
        unset($this->buffer[$this->current]);
        
        $this->skip = true;
        
        return $temp;
    }
}
