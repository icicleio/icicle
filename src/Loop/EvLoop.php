<?php
namespace Icicle\Loop;

use Icicle\Exception\UnsupportedError;
use Icicle\Loop\Manager\{IoManager, SignalManager, TimerManager};
use Icicle\Loop\Manager\Ev\{EvIoManager, EvSignalManager, EvTimerManager};

/**
 * Uses the ev extension to poll sockets for I/O and create timers.
 */
class EvLoop extends AbstractLoop
{
    /**
     * @var \EvLoop
     */
    private $loop;

    /**
     * @return bool True if EvLoop can be used, false otherwise.
     */
    public static function enabled(): bool
    {
        return extension_loaded('ev');
    }

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param \EvLoop|null $loop Use null for an EvLoop object to be automatically created.
     *
     * @throws \Icicle\Exception\UnsupportedError If the event extension is not loaded.
     */
    public function __construct(bool $enableSignals = true, \EvLoop $loop = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedError(__CLASS__ . ' requires the ev extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->loop = $loop ?: new \EvLoop();

        parent::__construct($enableSignals);
    }

    /**
     * @return  \EvLoop
     *
     * @codeCoverageIgnore
     */
    public function getEvLoop(): \EvLoop
    {
        return $this->loop;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch(bool $blocking)
    {
        $flags = \Ev::RUN_ONCE;
        
        if (!$blocking) {
            $flags |= \Ev::RUN_NOWAIT;
        }

        $this->loop->run($flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * Calls loopFork() on the EvLoop object.
     */
    public function reInit()
    {
        $this->loop->loopFork();
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager(): IoManager
    {
        return new EvIoManager($this, \Ev::READ);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(): IoManager
    {
        return new EvIoManager($this, \Ev::WRITE);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(): TimerManager
    {
        return new EvTimerManager($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(): SignalManager
    {
        return new EvSignalManager($this);
    }
}
