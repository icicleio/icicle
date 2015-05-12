<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Events\Manager\ImmediateManagerInterface;
use Icicle\Loop\Events\Manager\SocketManagerInterface;
use Icicle\Loop\Events\Manager\TimerManagerInterface;

/**
 * Default event factory implementation.
 */
class EventFactory implements EventFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function socket(SocketManagerInterface $manager, $resource, callable $callback)
    {
        return new SocketEvent($manager, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function timer(TimerManagerInterface $manager, callable $callback, $interval, $periodic = false, array $args = null)
    {
        return new Timer($manager, $callback, $interval, $periodic, $args);
    }
    
    /**
     * @inheritdoc
     */
    public function immediate(ImmediateManagerInterface $manager, callable $callback, array $args = null)
    {
        return new Immediate($manager, $callback, $args);
    }
}
