<?php
namespace Icicle\EventEmitter;

use Icicle\EventEmitter\Exception\InvalidEventException;

trait EventEmitterTrait
{
    /**
     * @var callable[string|int][string]
     */
    private $listeners = [];
    
    /**
     * @param   string|int $event
     */
    protected function createEvent($event)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
    }
    
/*
    protected function removeEvent($event)
    {
        unset($this->listeners[$event]);
    }
*/
    
    /**
     * @inheritdoc
     */
    public function addListener($event, callable $listener, $once = false)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
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
     * @inheritdoc
     */
    public function on($event, callable $listener)
    {
        return $this->addListener($event, $listener, false);
    }
    
    /**
     * @inheritdoc
     */
    public function once($event, callable $listener)
    {
        return $this->addListener($event, $listener, true);
    }
    
    /**
     * @inheritdoc
     */
    public function removeListener($event, callable $listener)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
        }
        
        if (isset($this->listeners[$event])) {
            $index = $this->getListenerIndex($listener);
            unset($this->listeners[$event][$index]);
        }
        
        return $this;
    }
    
    /**
     * @inheritdoc
     */
    public function off($event, callable $listener)
    {
        return $this->removeListener($event, $listener);
    }
    
    /**
     * @inheritdoc
     */
    public function removeAllListeners($event = null)
    {
        if (null === $event) {
            foreach ($this->listeners as $event => $listeners) {
                $this->listeners[$event] = [];
            }
        } else {
            if (!isset($this->listeners[$event])) {
                throw new InvalidEventException($event);
            }
            
            $this->listeners[$event] = [];
        }
        
        return $this;
    }
    
    /**
     * @inheritdoc
     */
    public function getListeners($event)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
        }
        
        return $this->listeners[$event];
    }
    
    /**
     * @inheritdoc
     */
    public function getListenerCount($event)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
        }
        
        return count($this->listeners[$event]);
    }
    
    /**
     * @inheritdoc
     */
    protected function emit($event /* , ...$args */)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
        }
        
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
        if (is_object($listener)) { // Closure or callable object.
            return spl_object_hash($listener);
        }
        
        if (is_array($listener)) { // Object/static method.
            return (is_object($listener[0]) ? spl_object_hash($listener[0]) : $listener[0]) . '::' . $listener[1];
        }
        
        return $listener; // Named function.
    }
}
