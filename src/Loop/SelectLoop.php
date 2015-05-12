<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\Select\SocketManager;
use Icicle\Loop\Events\Manager\Select\TimerManager;
use Icicle\Loop\Events\Manager\SocketManagerInterface;
use Icicle\Loop\Events\Manager\TimerManagerInterface;

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
     * @return  bool
     */
    public static function enabled()
    {
        return true;
    }
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     */
    public function __construct(EventFactoryInterface $eventFactory = null)
    {
        parent::__construct($eventFactory);
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $this->createEvent($signal);
                pcntl_signal($signal, $callback);
            }
        }
    }
    
    /**
     * @inheritdoc
     */
    public function reInit() { /* Nothing to be done after fork. */ }
    
    /**
     * @inheritdoc
     */
    protected function dispatch(
        SocketManagerInterface $pollManager,
        SocketManagerInterface $awaitManager,
        TimerManagerInterface $timerManager,
        $blocking
    ) {
        $timeout = $blocking ? $timerManager->getInterval() : 0;

        $this->select($pollManager, $awaitManager, $timeout); // Select available sockets for reading or writing.
        
        $timerManager->tick(); // Call any pending timers.
        
        if ($this->signalHandlingEnabled()) {
            pcntl_signal_dispatch(); // Dispatch any signals that may have arrived.
        }
    }
    
    /**
     * @param   \Icicle\Loop\Events\Manager\SocketManagerInterface $pollManager
     * @param   \Icicle\Loop\Events\Manager\SocketManagerInterface $awaitManager
     * @param   int|float|null $timeout
     *
     * @return  bool
     */
    protected function select(SocketManagerInterface $pollManager, SocketManagerInterface $awaitManager, $timeout)
    {
        // Use stream_select() if there are any streams in the loop.
        if (!$pollManager->isEmpty() || !$awaitManager->isEmpty()) {
            $seconds = (int) $timeout;
            $microseconds = ($timeout - $seconds) * self::MICROSEC_PER_SEC;
            
            $read = [];
            $write = [];
            $except = null;
            
            foreach ($pollManager->getPending() as $resource) {
                $read[] = $resource;
            }
            
            foreach ($awaitManager->getPending() as $resource) {
                $write[] = $resource;
            }
            
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
     * @inheritdoc
     */
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory);
    }
    
    /**
     * @inheritdoc
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory);
    }
    
    /**
     * @inheritdoc
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return new TimerManager($factory);
    }
}
