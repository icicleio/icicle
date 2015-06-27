<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Manager\Select\SignalManager;
use Icicle\Loop\Manager\Select\SocketManager;
use Icicle\Loop\Manager\Select\TimerManager;
use Icicle\Loop\Manager\SocketManagerInterface;

/**
 * Uses stream_select(), time_nanosleep(), and pcntl_signal_dispatch() (if available) to implement an event loop that
 * can poll sockets for I/O, create timers, and handle signals.
 */
class SelectLoop extends AbstractLoop
{
    const MICROSEC_PER_SEC = 1e6;
    const NANOSEC_PER_SEC = 1e9;
    
    /**
     * Always returns true for this class, since this class only requires core PHP functions.
     *
     * @return bool
     */
    public static function enabled()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function reInit() { /* Nothing to be done after fork. */ }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        $timerManager = $this->getTimerManager();

        $timeout = $blocking ? $timerManager->getInterval() : 0;

        // Select available sockets for reading or writing.
        $this->select($this->getPollManager(), $this->getAwaitManager(), $timeout);
        
        $timerManager->tick(); // Call any pending timers.
        
        if ($this->signalHandlingEnabled()) {
            $this->getSignalManager()->tick(); // Dispatch any signals that may have arrived.
        }
    }
    
    /**
     * @param \Icicle\Loop\Manager\SocketManagerInterface $pollManager
     * @param \Icicle\Loop\Manager\SocketManagerInterface $awaitManager
     * @param int|float|null $timeout
     */
    protected function select(SocketManagerInterface $pollManager, SocketManagerInterface $awaitManager, $timeout)
    {
        // Use stream_select() if there are any streams in the loop.
        if (!$pollManager->isEmpty() || !$awaitManager->isEmpty()) {
            $seconds = (int) $timeout;
            $microseconds = ($timeout - $seconds) * self::MICROSEC_PER_SEC;
            
            $read = $pollManager->getPending();
            $write = $awaitManager->getPending();
            $except = null;

            // Error reporting suppressed since stream_select() emits an E_WARNING if it is interrupted by a signal. *sigh*
            $count = @stream_select($read, $write, $except, null === $timeout ? null : $seconds, $microseconds);
            
            if ($count) {
                $pollManager->handle($read);
                $awaitManager->handle($write);
            }

            return;
        }

        // Otherwise sleep with time_nanosleep() if $timeout > 0.
        if (0 < $timeout) {
            $seconds = (int) $timeout;
            $nanoseconds = ($timeout - $seconds) * self::NANOSEC_PER_SEC;
        
            time_nanosleep($seconds, $nanoseconds); // Will be interrupted if a signal is received.
        }
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return new TimerManager($factory);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(EventFactoryInterface $factory)
    {
        return new SignalManager($this, $factory);
    }
}
