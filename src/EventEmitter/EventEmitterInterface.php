<?php
namespace Icicle\EventEmitter;

interface EventEmitterInterface
{
    /**
     * Adds a listener function that is called each time the event is emitted.
     *
     * @param   string|int $event
     * @param   callable $listener
     * @param   bool $once Set to true for the listener to be called only the next time the event is emitted.
     *
     * @return  $this
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function addListener($event, callable $listener, $once = false);
    
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
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function on($event, callable $listener);
    
    /**
     * Adds a one time listener that is called only the next time the event is emitted. Alias of
     * addListener() with the $once parameter set to true.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  $this
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function once($event, callable $listener);
    
    /**
     * Removes the listener from the event.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  $this
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function removeListener($event, callable $listener);
    
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
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function off($event, callable $listener);
    
    /**
     * Removes all listeners from the event or all events if no event is given.
     *
     * @param   string|int|null $event Event name or null to remove all event listeners.
     *
     * @return  $this
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function removeAllListeners($event = null);
    
    /**
     * Returns all listeners for the event.
     *
     * @param   string|int $event
     *
     * @return  callable[string] Array of event listeners.
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function getListeners($event);
    
    /**
     * Determines the number of listeners on an event.
     *
     * @param   string $event
     *
     * @return  int Number of listeners defined.
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     *
     * @api
     */
    public function getListenerCount($event);
    
    /**
     * Calls all event listeners for the given event name, passing all other arguments given to this function as arguments
     * to the event listeners.
     *
     * @param   string|int $event
     * @param   mixed ...$args
     *
     * @return  bool True if any listeners were called, false if no listeners were called.
     *
     * @throws  \Icicle\EventEmitter\Exception\InvalidEventException If the event name does not exist.
     */
    public function emit($event /* , ...$args */);
}
