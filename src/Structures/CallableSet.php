<?php
namespace Icicle\Structures;

class CallableSet implements \Countable
{
    /**
     * @var callable[]
     */
    private $callbacks = [];
    
    /**
     * Adds the callback to the set if it is not already part of the set. That is, adding the same
     * callback twice will not result in the function being called twice per invocation.
     *
     * @param   callable $callback
     * @param   bool $once Set to true for the callback to only be called on the next invocation.
     */
    public function add(callable $callback, $once = false)
    {
        $index = $this->index($callback);
        
        if (!isset($this->callbacks[$index])) {
            if ($once) {
                $callback = function (/* ...$args */) use ($callback) {
                    $this->remove($callback);
                    call_user_func_array($callback, func_get_args());
                };
            }
            
            $this->callbacks[$index] = $callback;
        }
    }
    
    /**
     * Alias of add() with $once set to true.
     *
     * @param   callable $callback
     */
    public function once(callable $callback)
    {
        $this->add($callback, true);
    }
    
    /**
     * Removes the callback from the set.
     *
     * @param   callable $callback
     */
    public function remove(callable $callback)
    {
        unset($this->callbacks[$this->index($callback)]);
    }
    
    /**
     * Removes all callbacks from the set.
     */
    public function removeAll()
    {
        $this->callbacks = [];
    }
    
    /**
     * Determines if a callback is part of the set.
     *
     * @param   callable $callback
     *
     * @return  bool
     */
    public function has(callable $callback)
    {
        return isset($this->callbacks[$this->index($callback)]);
    }
    
    /**
     * Calls each callback function in the set with the same arguments passed to this method.
     *
     * @param   mixed ...$args Arguments given to each callback.
     *
     * @return  bool Returns true if any callbacks were invoked, false if the set was empty.
     */
    public function call(/* ...$args */)
    {
        return $this->callWithArrayArgs(func_get_args());
    }
    
    /**
     * Calls each callback function in the set using the arguments given in the array.
     *
     * @param   array $args Arguments to be given to each callback.
     *
     * @return  bool Returns true if any callbacks were invoked, false if the set was empty.
     */
    public function callWithArrayArgs(array $args)
    {
        if (empty($this->callbacks)) {
            return false;
        }
        
        foreach ($this->callbacks as $callback) {
            call_user_func_array($callback, $args);
        }
        
        return true;
    }
    
    /**
     * Alias of call().
     *
     * @param   mixed ...$args
     *
     * @return  bool
     */
    public function __invoke(/* ...$args */)
    {
        return $this->callWithArrayArgs(func_get_args());
    }
    
    /**
     * @return  int Number of callbacks in the set.
     */
    public function count()
    {
        return count($this->callbacks);
    }
    
    /**
     * Generates a unique, repeatable string for the given callback.
     *
     * @param   callable $callback
     *
     * @return  string
     */
    protected function index(callable $callback)
    {
        if (is_object($callback)) {
            return spl_object_hash($callback); // Closure or callable object.
        }
        
        if (is_array($callback)) { // Concatenating :: to match against string of format ClassName::staticMethod.
            return (is_object($callback[0]) ? spl_object_hash($callback[0]) : $callback[0]) . '::' . $callback[1]; // Object/static method.
        }
        
        return $callback; // Function or static method name.
    }
}
