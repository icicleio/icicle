<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\UnsupportedError;
use Icicle\Loop\Manager\Ev\SignalManager;
use Icicle\Loop\Manager\Ev\SocketManager;
use Icicle\Loop\Manager\Ev\TimerManager;

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
     * Determines if the ev extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('ev');
    }

    /**
     * @param bool $enableSignals True to enable signal handling, false to disable.
     * @param \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     * @param \EvLoop|null $loop Use null for an EvLoop object to be automatically created.
     *
     * @throws \Icicle\Loop\Exception\UnsupportedError If the event extension is not loaded.
     */
    public function __construct($enableSignals = true, EventFactoryInterface $eventFactory = null, \EvLoop $loop = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedError(__CLASS__ . ' requires the ev extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->loop = $loop ?: new \EvLoop();

        parent::__construct($enableSignals, $eventFactory);
    }

    /**
     * @return  \EvLoop
     *
     * @codeCoverageIgnore
     */
    public function getEvLoop()
    {
        return $this->loop;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
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
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory, \Ev::READ);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new SocketManager($this, $factory, \Ev::WRITE);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return new TimerManager($this, $factory);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(EventFactoryInterface $factory)
    {
        return new SignalManager($this, $factory);
    }
}
