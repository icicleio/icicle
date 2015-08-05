<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Manager\Select\SignalManager;
use Icicle\Loop\Manager\Select\SocketManager;
use Icicle\Loop\Manager\Select\TimerManager;

/**
 * Uses stream_select(), time_nanosleep(), and pcntl_signal_dispatch() (if available) to implement an event loop that
 * can poll sockets for I/O, create timers, and handle signals.
 */
class SelectLoop extends AbstractLoop
{
    const MICROSEC_PER_SEC = 1e6;
    const NANOSEC_PER_SEC = 1e9;

    /**
     * @var \Icicle\Loop\Manager\Select\SocketManager
     */
    private $pollManager;

    /**
     * @var \Icicle\Loop\Manager\Select\SocketManager
     */
    private $awaitManager;

    /**
     * @var \Icicle\Loop\Manager\Select\TimerManager
     */
    private $timerManager;

    /**
     * @var \Icicle\Loop\Manager\Select\SignalManager
     */
    private $signalManager;

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
        $timeout = $blocking ? $this->timerManager->getInterval() : 0;

        // Select available sockets for reading or writing.
        $this->select($timeout);
        
        $this->timerManager->tick(); // Call any pending timers.
        
        if ($this->signalHandlingEnabled()) {
            $this->signalManager->tick(); // Dispatch any signals that may have arrived.
        }
    }
    
    /**
     * @param int|float|null $timeout
     */
    protected function select($timeout)
    {
        // Use stream_select() if there are any streams in the loop.
        if (!$this->pollManager->isEmpty() || !$this->awaitManager->isEmpty()) {
            $seconds = (int) $timeout;
            $microseconds = ($timeout - $seconds) * self::MICROSEC_PER_SEC;
            
            $read = $this->pollManager->getPending();
            $write = $this->awaitManager->getPending();
            $except = null;

            // Error reporting suppressed since stream_select() emits an E_WARNING if it is interrupted by a signal. *sigh*
            $count = @stream_select($read, $write, $except, null === $timeout ? null : $seconds, $microseconds);
            
            if ($count) {
                $this->pollManager->handle($read);
                $this->awaitManager->handle($write);
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
        return $this->pollManager = new SocketManager($this, $factory);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return $this->awaitManager = new SocketManager($this, $factory);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return $this->timerManager = new TimerManager($this, $factory);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(EventFactoryInterface $factory)
    {
        return $this->signalManager = new SignalManager($this, $factory);
    }
}
