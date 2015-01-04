<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\LoopInterface;

interface EventFactoryInterface
{
    /**
     * @param   LoopInterface $loop
     * @param   resource $resource Socket resource.
     * @param   callable $callback Callback function invoked when data is available on the socket.
     *
     * @return  PollInterface
     */
    public function createPoll(LoopInterface $loop, $resource, callable $callback);
    
    /**
     * @param   LoopInterface $loop
     * @param   resource $resource Socket resource.
     * @param   callable $callback Callback function invoked when data may be written (non-blocking) to the socket.
     *
     * @return  AwaitInterface
     */
    public function createAwait(LoopInterface $loop, $resource, callable $callback);
    
    /**
     * @param   LoopInterface $loop
     * @param   callable $callback Callback function invoked after the interval elapses.
     * @param   int|float $interval Timer interval.
     * @param   bool $periodic Set to true to repeat the timer every interval seconds, false for a one-time timer.
     * @param   array $args Arguments to pass to the callback function.
     *
     * @return  TimerInterface
     */
    public function createTimer(LoopInterface $loop, callable $callback, $interval, $periodic = false, array $args = null);
    
    /**
     * @param   LoopInterface $loop
     * @param   callable $callback Callback function to be invoked.
     *
     * @return  ImmediateInterface
     */
    public function createImmediate(LoopInterface $loop, callable $callback, array $args = null);
}