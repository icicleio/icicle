<?php
namespace Icicle\Loop\Manager\Select;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\LoopInterface;
use Icicle\Loop\Manager\AbstractSignalManager;

class SignalManager extends AbstractSignalManager
{
    /**
     * @param \Icicle\Loop\LoopInterface
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory)
    {
        parent::__construct($loop, $factory);

        $callback = $this->createSignalCallback();

        foreach ($this->getSignalList() as $signal) {
            pcntl_signal($signal, $callback);
        }
    }

    /**
     * Dispatch any signals that have arrived.
     *
     * @internal
     */
    public function tick()
    {
        pcntl_signal_dispatch();
    }
}
