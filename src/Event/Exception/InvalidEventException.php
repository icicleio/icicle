<?php
namespace Icicle\Event\Exception;

class InvalidEventException extends InvalidArgumentException
{
    /**
     * @var mixed
     */
    private $event;
    
    /**
     * @param   mixed $event
     */
    public function __construct($event)
    {
        parent::__construct("Event does not exist: {$event}.");
        
        $this->event = $event;
    }
    
    /**
     * @return  callable
     */
    public function getEvent()
    {
        return $this->event;
    }
}
