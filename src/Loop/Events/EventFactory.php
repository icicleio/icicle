<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\AwaitManagerInterface;
use Icicle\Loop\Manager\ImmediateManagerInterface;
use Icicle\Loop\Manager\PollManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;

/**
 * Default event factory implementation.
 */
class EventFactory implements EventFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function createPoll(PollManagerInterface $manager, $resource, callable $callback)
    {
        return new Poll($manager, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function createAwait(AwaitManagerInterface $manager, $resource, callable $callback)
    {
        return new Await($manager, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function createTimer(TimerManagerInterface $manager, callable $callback, $interval, $periodic = false, array $args = null)
    {
        return new Timer($manager, $callback, $interval, $periodic, $args);
    }
    
    /**
     * @inheritdoc
     */
    public function createImmediate(ImmediateManagerInterface $manager, callable $callback, array $args = null)
    {
        return new Immediate($manager, $callback, $args);
    }
}
