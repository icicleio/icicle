<?php
namespace Icicle\Event;

trait EventEmitterTrait
{
    /**
     * @var callable[mixed][string]
     */
    private $listeners = [];
    
    /**
     * {@inheritdoc}
     */
    public function addListener($event, callable $listener, $once = false)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        $index = $this->getListenerIndex($listener);
        
        if (!isset($this->listeners[$event][$index])) {
            if ($once) {
                $listener = function (/* ...$args */) use ($event, $listener) {
                    $this->removeListener($event, $listener);
                    call_user_func_array($listener, func_get_args());
                };
            }
            
            $this->listeners[$event][$index] = $listener;
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function on($event, callable $listener)
    {
        return $this->addListener($event, $listener, false);
    }
    
    /**
     * {@inheritdoc}
     */
    public function once($event, callable $listener)
    {
        return $this->addListener($event, $listener, true);
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeListener($event, callable $listener)
    {
        if (isset($this->listeners[$event])) {
            $index = $this->getListenerIndex($listener);
            unset($this->listeners[$event][$index]);
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function off($event, callable $listener)
    {
        return $this->removeListener($event, $listener);
    }
    
    /**
     * {@inheritdoc}
     */
    public function removeAllListeners($event = null)
    {
        if (null === $event) {
            $this->listeners = [];
        } else {
            unset($this->listeners[$event]);
        }
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getListeners($event)
    {
        return isset($this->listeners[$event]) ? $this->listeners[$event] : [];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getListenerCount($event)
    {
        return isset($this->listeners[$event]) ? count($this->listeners[$event]) : 0;
    }
    
    /**
     * {@inheritdoc}
     */
    public function emit($event /* , ...$args */)
    {
        if (empty($this->listeners[$event])) {
            return false;
        }
        
        $args = array_slice(func_get_args(), 1);
        
        foreach ($this->listeners[$event] as $listener) {
            call_user_func_array($listener, $args);
        }
        
        return true;
    }
    
    /**
     * Generates a unique, repeatable string for the given listener.
     *
     * @param   callable $listener
     *
     * @return  string
     */
    protected function getListenerIndex(callable $listener)
    {
        if (is_object($listener)) {
            return spl_object_hash($listener); // Closure or callable object.
        }
        
        if (is_array($listener)) { // Concatenating :: to match against string of format ClassName::staticMethod.
            return (is_object($listener[0]) ? spl_object_hash($listener[0]) : $listener[0]) . '::' . $listener[1]; // Object/static method.
        }
        
        return $listener; // Function or static method name.
    }
}
