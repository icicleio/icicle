<?php
namespace Icicle\Event;

interface EventEmitterInterface
{
    /**
     * Adds a listener function that is called each time the event is emitted.
     *
     * @param   string|int $event
     * @param   callable $listener
     * @param   bool $once Set to true for the listener to be called only the next time the event is emitted.
     *
     * @return  self
     */
    public function addListener($event, callable $listener);
    
    /**
     * Alias of addListener() without the $once parameter.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  self
     */
    public function on($event, callable $listener);
    
    /**
     * Adds a one time listener that is called only the next time the event is emitted. Alias of
     * addListener() with the $once parameter set to true.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  self
     */
    public function once($event, callable $listener);
    
    /**
     * Removes the listener from the event.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  self
     */
    public function removeListener($event, callable $listener);
    
    /**
     * Alias of removeListener().
     *
     * @param   string|int
     * @param   callable $listener
     *
     * @return  self
     */
    public function off($event, callable $listener);
    
    /**
     * Removes all listeners from the event or all events if no event is given.
     *
     * @param   string|int|null $event Event name or null to remove all event listeners.
     *
     * @return  self
     */
    public function removeAllListeners($event = null);
    
    /**
     * Returns all listeners for the event.
     *
     * @param   string|int $event
     *
     * @return  callable[string] Array of event listeners.
     */
    public function getListeners($event);
    
    /**
     * Determines the number of listeners on an event.
     *
     * @param   string $event
     *
     * @return  int Number of listeners defined.
     */
    public function getListenerCount($event);
    
    /**
     * Emits an event, calling each listener with the given arguments.
     *
     * @param   string $event Event name.
     * @param   mixed ...$args Arguments passed to the listening function.
     *
     * @return  bool True if any listeners were defined on the event, false otherwise.
     */
    public function emit($event /* , ...$args */);
}
