<?php
namespace Icicle\Loop\Events\Manager\Libevent;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\AbstractSignalManager;
use Icicle\Loop\LoopInterface;

class SignalManager extends AbstractSignalManager
{
    /**
     * @var resource[]
     */
    private $events = [];

    /**
     * @param \Icicle\Loop\LoopInterface $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     * @param resource $base
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory, $base)
    {
        parent::__construct($loop, $factory);

        $callback = $this->createSignalCallback();

        foreach ($this->getSignalList() as $signo) {
            $event = event_new();
            event_set($event, $signo, EV_SIGNAL | EV_PERSIST, $callback);
            event_base_set($event, $base);
            event_add($event);
            $this->events[$signo] = $event;
        }
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            event_free($event);
        }
    }
}