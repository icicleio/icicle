<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\ImmediateManagerInterface;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;

interface EventFactoryInterface
{
    /**
     * @param   SocketManagerInterface $manager
     * @param   resource $resource Socket resource.
     * @param   callable $callback Callback function invoked when data is available on the socket.
     *
     * @return  SocketEventInterface
     */
    public function socket(SocketManagerInterface $manager, $resource, callable $callback);
    
    /**
     * @param   TimerManagerInterface $manager
     * @param   callable $callback Callback function invoked after the interval elapses.
     * @param   int|float $interval Timer interval.
     * @param   bool $periodic Set to true to repeat the timer every interval seconds, false for a one-time timer.
     * @param   array $args Arguments to pass to the callback function.
     *
     * @return  TimerInterface
     */
    public function timer(TimerManagerInterface $manager, callable $callback, $interval, $periodic = false, array $args = null);
    
    /**
     * @param   ImmediateManagerInterface $manager
     * @param   callable $callback Callback function to be invoked.
     * @param   array $args Arguments to pass to the callback function.
     *
     * @return  ImmediateInterface
     */
    public function immediate(ImmediateManagerInterface $manager, callable $callback, array $args = null);
}