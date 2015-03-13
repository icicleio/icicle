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
        
        return $this;
    }
    
    /**
     * Adds a listener function that is called each time the event is emitted.
     *
     * @param   string|int $event
     * @param   callable $listener
     * @param   bool $once Set to true for the listener to be called only the next time the event is emitted.
     *
     * @return  $this
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
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
     * Alias of addListener() without the $once parameter.
     *
     * @see     addListener()
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  $this
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function on($event, callable $listener)
    {
        return $this->addListener($event, $listener, false);
    }
    
    /**
     * Adds a one time listener that is called only the next time the event is emitted. Alias of
     * addListener() with the $once parameter set to true.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  $this
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function once($event, callable $listener)
    {
        return $this->addListener($event, $listener, true);
    }
    
    /**
     * Removes the listener from the event.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  $this
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
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
     * Alias of removeListener().
     *
     * @see     removeListener()
     *
     * @param   string|int
     * @param   callable $listener
     *
     * @return  $this
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function off($event, callable $listener)
    {
        return $this->removeListener($event, $listener);
    }
    
    /**
     * Removes all listeners from the event or all events if no event is given.
     *
     * @param   string|int|null $event Event name or null to remove all event listeners.
     *
     * @return  $this
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
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
     * Returns all listeners for the event.
     *
     * @param   string|int $event
     *
     * @return  callable[string] Array of event listeners.
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function getListeners($event)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
        }
        
        return $this->listeners[$event];
    }
    
    /**
     * Determines the number of listeners on an event.
     *
     * @param   string $event
     *
     * @return  int Number of listeners defined.
     *
     * @throws  InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function getListenerCount($event)
    {
        if (!isset($this->listeners[$event])) {
            throw new InvalidEventException($event);
        }
        
        return count($this->listeners[$event]);
    }
    
    /**
     * Calls all event listeners for the given event name, passing all other arguments given to this function as arguments
     * to the event listeners.
     *
     * @param   string|int $event
     * @param   mixed ...$args
     *
     * @return  bool True if any listeners were called, false if no listeners were called.
     *
     * @throws  InvalidEventException If the event name does not exist.
     */
    public function emit($event /* , ...$args */)
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
     * @return  string Unique identifier for the callable.
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
