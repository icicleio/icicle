<?php
namespace Icicle\EventEmitter\Exception;

class InvalidEventException extends InvalidArgumentException
{
    /**
     * @var string|int
     */
    private $event;
    
    /**
     * @param   string|int $event
     */
    public function __construct($event)
    {
        parent::__construct("Event '{$event}' does not exist.");
        
        $this->event = $event;
    }
    
    /**
     * @return  string|int
     */
    public function getEvent()
    {
        return $this->event;
    }
}
