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
     * @return  self
     *
     * @throws  InvalidEventException Thrown if the event name does not exist.
     *
     * @api
     */
    public function addListener($event, callable $listener);
    
    /**
     * Alias of addListener() without the $once parameter.
     *
     * @param   string|int $event
     * @param   callable $listener
     *
     * @return  self
     *
     * @throws  InvalidEventException Thrown if the event name does not exist.
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
     * @return  self
     *
     * @throws  InvalidEventException Thrown if the event name does not exist.
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
     * @return  self
     *
     * @throws  InvalidEventException Thrown if the event name does not exist.
     *
     * @api
     */
    public function removeListener($event, callable $listener);
    
    /**
     * Alias of removeListener().
     *
     * @param   string|int
     * @param   callable $listener
     *
     * @return  self
     *
     * @throws  InvalidEventException Thrown if the event name does not exist.
     *
     * @api
     */
    public function off($event, callable $listener);
    
    /**
     * Removes all listeners from the event or all events if no event is given.
     *
     * @param   string|int|null $event Event name or null to remove all event listeners.
     *
     * @return  self
     *
     * @throws  InvalidEventException Thrown if the event name does not exist.
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
     * @throws  InvalidEventException Thrown if the event name does not exist.
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
     * @throws  InvalidEventException Thrown if the event name does not exist.
     *
     * @api
     */
    public function getListenerCount($event);
}
