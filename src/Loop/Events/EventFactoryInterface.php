<?php
namespace Icicle\Loop\Events;

use Icicle\Loop\Events\Manager\ImmediateManagerInterface;
use Icicle\Loop\Events\Manager\SocketManagerInterface;
use Icicle\Loop\Events\Manager\TimerManagerInterface;

interface EventFactoryInterface
{
    /**
     * @param   \Icicle\Loop\Events\Manager\SocketManagerInterface $manager
     * @param   resource $resource Socket resource.
     * @param   callable $callback Callback function invoked when data is available on the socket.
     *
     * @return  \Icicle\Loop\Events\SocketEventInterface
     */
    public function socket(SocketManagerInterface $manager, $resource, callable $callback);
    
    /**
     * @param   \Icicle\Loop\Events\Manager\TimerManagerInterface $manager
     * @param   callable $callback Callback function invoked after the interval elapses.
     * @param   int|float $interval Timer interval.
     * @param   bool $periodic Set to true to repeat the timer every interval seconds, false for a one-time timer.
     * @param   mixed[]|null $args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\TimerInterface
     */
    public function timer(TimerManagerInterface $manager, callable $callback, $interval, $periodic = false, array $args = null);
    
    /**
     * @param   \Icicle\Loop\Events\Manager\ImmediateManagerInterface $manager
     * @param   callable $callback Callback function to be invoked.
     * @param   mixed[]|null $args Arguments to pass to the callback function.
     *
     * @return  \Icicle\Loop\Events\ImmediateInterface
     */
    public function immediate(ImmediateManagerInterface $manager, callable $callback, array $args = null);
}