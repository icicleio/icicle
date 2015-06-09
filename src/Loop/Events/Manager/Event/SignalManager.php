<?php
namespace Icicle\Loop\Events\Manager\Event;

use Event;
use EventBase;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\AbstractSignalManager;
use Icicle\Loop\LoopInterface;

class SignalManager extends AbstractSignalManager
{
    /**
     * @var \Event[]
     */
    private $events = [];

    /**
     * @param \Icicle\Loop\LoopInterface $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param \EventBase $base
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory, EventBase $base)
    {
        parent::__construct($loop, $factory);

        $callback = $this->createSignalCallback();

        foreach ($this->getSignalList() as $signal) {
            $event = new Event($base, $signal, Event::SIGNAL | Event::PERSIST, $callback);
            $event->add();
            $this->events[$signal] = $event;
        }
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            $event->free();
        }
    }
}