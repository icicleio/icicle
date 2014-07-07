<?php
namespace Icicle\Structures;

/**
 */
class Buffer implements \ArrayAccess, \Countable, \IteratorAggregate
{
    /**
     * @var     string
     */
    private $data = '';
    
    /**
     * @param   string $data Intialize buffer with the given string.
     */
    public function __construct($data = '')
    {
        $this->data = (string) $data;
    }
    
    /**
     * Current length of the buffer.
     *
     * @return  int
     */
    public function getLength()
    {
        return strlen($this->data);
    }
    
    /**
     * @return  int
     */
    public function count()
    {
        return $this->getLength();
    }
    
    /**
     * Determines if the buffer is empty.
     *
     * @return  bool
     */
    public function isEmpty()
    {
        return empty($this->data);
    }
    
    /**
     * Pushes the given string onto the end of the buffer.
     *
     * @param   string $data
     */
    public function push($data)
    {
        $this->data .= $data;
    }
    
    /**
     * Puts the given string at the beginning of the buffer.
     *
     * @param   string $data
     */
    public function unshift($data)
    {
        $this->data = $data . $this->data;
    }
    
    /**
     * @param   int $length
     *
     * @return  string|null
     */
    public function shift($length)
    {
        return $this->remove($length, 0);
    }
    
    /**
     * Returns the given number of characters (at most) from the buffer without removing them from the buffer.
     *
     * @param   int $length
     * @param   int $offset
     *
     * @return  string
     */
    public function peek($length, $offset = 0)
    {
        $length = (int) $length;
        if (0 >= $length) {
            return '';
        }
        
        $offset = (int) $offset;
        if (0 > $offset) {
            $offset = 0;
        }
        
        $result = (string) substr($this->data, $offset, $length);
        
        return $result;
    }
    
    /**
     * @param   int $length
     *
     * @return  string
     */
    public function pop($length)
    {
        $length = (int) $length;
        if (0 >= $length) {
            return '';
        }
        
        $buffer = (string) substr($this->data, $length * -1);
        
        $this->data = (string) substr($this->data, 0, $length * -1);
        
        return $buffer;
    }
    
    /**
     * Removes and returns the given number of characters (at most) from the buffer.
     *
     * @param   int $length
     * @param   int $offset
     *
     * @return  string
     */
    public function remove($length, $offset = 0)
    {
        $length = (int) $length;
        if (0 >= $length) {
            return '';
        }
        
        $offset = (int) $offset;
        if (0 > $offset) {
            $offset = 0;
        }
        
        $buffer = (string) substr($this->data, $offset, $length);
        
        if (0 === $offset) {
            $this->data = (string) substr($this->data, $length);
        } else {
            $this->data = (string) (substr($this->data, 0, $offset) . substr($this->data, $offset + $length));
        }
        
        return $buffer;
    }
    
    /**
     * Removes and returns all data in the buffer.
     *
     * @return  string
     */
    public function drain()
    {
        $buffer = $this->data;
        $this->data = '';
        return $buffer;
    }
    
    /**
     * Inserts the string at the given position in the buffer.
     *
     * @param   string $string
     * @param   int $position
     */
    public function insert($string, $position)
    {
        $this->data = substr_replace($this->data, $string, $position, 0);
    }
    
    /**
     * Replaces all occurances of $search with $replace. See str_replace() function.
     *
     * @param   mixed $search
     * @param   mixed $replace
     *
     * @return  int Number of replacements performed.
     */
    public function replace($search, $replace)
    {
        $this->data = str_replace($search, $replace, $this->data, $count);
        
        return $count;
    }
    
    /**
     * Returns the position of the given pattern in the buffer if it exists, or false if it does not.
     *
     * @return  int|bool
     */
    public function search($string, $reverse = false)
    {
        if ($reverse) {
            return strrpos($this->data, $string);
        }
        
        return strpos($this->data, $string);
    }
    
    /**
     * Determines if the buffer contains the given position.
     *
     * @param   int $index
     *
     * @return  bool
     */
    public function offsetExists($index)
    {
        return isset($this->data[$index]);
    }
    
    /**
     * Returns the character in the buffer at the given position.
     *
     * @param   int $index
     *
     * @return  string
     */
    public function offsetGet($index)
    {
        return $this->data[$index];
    }
    
    /**
     * Replaces the character in the buffer at the given position with the given string.
     *
     * @param   int $index
     * @param   string $data
     */
    public function offsetSet($index, $data)
    {
        $this->data = substr_replace($this->data, $data, $index, 1);
    }
    
    /**
     * Removes the character at the given index from the buffer.
     *
     * @param   int $index
     */
    public function offsetUnset($index)
    {
        if (isset($this->data[$index])) {
            $this->data = substr_replace($this->data, null, $index, 1);
        }
    }
    
    /**
     * @return  BufferIterator
     */
    public function getIterator()
    {
        return new BufferIterator($this);
    }
    
    /**
     * @return  string
     */
    public function __toString()
    {
        return $this->data;
    }
}

