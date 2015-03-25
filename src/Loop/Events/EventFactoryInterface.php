<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\AwaitManagerInterface;
use Icicle\Loop\Manager\ImmediateManagerInterface;
use Icicle\Loop\Manager\PollManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;

interface EventFactoryInterface
{
    /**
     * @param   PollManagerInterface $manager
     * @param   resource $resource Socket resource.
     * @param   callable $callback Callback function invoked when data is available on the socket.
     *
     * @return  PollInterface
     */
    public function createPoll(PollManagerInterface $manager, $resource, callable $callback);
    
    /**
     * @param   AwaitManagerInterface $manager
     * @param   resource $resource Socket resource.
     * @param   callable $callback Callback function invoked when data may be written (non-blocking) to the socket.
     *
     * @return  AwaitInterface
     */
    public function createAwait(AwaitManagerInterface $manager, $resource, callable $callback);
    
    /**
     * @param   TimerManagerInterface $manager
     * @param   callable $callback Callback function invoked after the interval elapses.
     * @param   int|float $interval Timer interval.
     * @param   bool $periodic Set to true to repeat the timer every interval seconds, false for a one-time timer.
     * @param   array $args Arguments to pass to the callback function.
     *
     * @return  TimerInterface
     */
    public function createTimer(TimerManagerInterface $manager, callable $callback, $interval, $periodic = false, array $args = null);
    
    /**
     * @param   ImmediateManagerInterface $manager
     * @param   callable $callback Callback function to be invoked.
     * @param   array $args Arguments to pass to the callback function.
     *
     * @return  ImmediateInterface
     */
    public function createImmediate(ImmediateManagerInterface $manager, callable $callback, array $args = null);
}