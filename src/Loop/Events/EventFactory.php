<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\{
    ImmediateManagerInterface,
    SignalManagerInterface,
    SocketManagerInterface,
    TimerManagerInterface
};

/**
 * Default event factory implementation.
 */
class EventFactory implements EventFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function socket(SocketManagerInterface $manager, $resource, callable $callback): SocketEventInterface
    {
        return new SocketEvent($manager, $resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timer(
        TimerManagerInterface$manager,
        float $interval,
        bool $periodic,
        callable $callback,
        array $args = null
    ): TimerInterface {
        return new Timer($manager, $interval, $periodic, $callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function immediate(
        ImmediateManagerInterface $manager,
        callable $callback,
        array $args = null
    ): ImmediateInterface {
        return new Immediate($manager, $callback, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function signal(SignalManagerInterface $manager, int $signo, callable $callback): SignalInterface
    {
        return new Signal($manager, $signo, $callback);
    }
}
