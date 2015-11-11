<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\EvLoop;
use Icicle\Loop\Manager\AbstractSignalManager;

class EvSignalManager extends AbstractSignalManager
{
    /**
     * @var \EvSignal[]
     */
    private $events = [];

    /**
     * @param \Icicle\Loop\EvLoop $loop
     */
    public function __construct(EvLoop $loop)
    {
        parent::__construct($loop);

        $callback = $this->createSignalCallback();

        $callback = function (\EvSignal $event) use ($callback) {
            $callback($event->signum);
        };

        $loop = $loop->getEvLoop();

        foreach ($this->getSignalList() as $signal) {
            $event = $loop->signal($signal, $callback);
            $this->events[$signal] = $event;
        }
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->events as $event) {
            $event->stop();
        }
    }
}