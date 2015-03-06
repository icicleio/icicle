<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\LoopInterface;

/**
 * Default event factory implementation.
 */
class EventFactory implements EventFactoryInterface
{
    /**
     * @inheritdoc
     */
    public function createPoll(LoopInterface $loop, $resource, callable $callback)
    {
        return new Poll($loop, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function createAwait(LoopInterface $loop, $resource, callable $callback)
    {
        return new Await($loop, $resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function createTimer(LoopInterface $loop, callable $callback, $interval, $periodic = false, array $args = null)
    {
        return new Timer($loop, $callback, $interval, $periodic, $args);
    }
    
    /**
     * @inheritdoc
     */
    public function createImmediate(LoopInterface $loop, callable $callback, array $args = null)
    {
        return new Immediate($loop, $callback, $args);
    }
}
