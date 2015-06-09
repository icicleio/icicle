<?php
namespace Icicle\Loop;

use Event;
use EventBase;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\Event\AwaitManager;
use Icicle\Loop\Events\Manager\Event\PollManager;
use Icicle\Loop\Events\Manager\Event\SignalManager;
use Icicle\Loop\Events\Manager\Event\TimerManager;
use Icicle\Loop\Exception\UnsupportedException;

/**
 * Uses the event extension to poll sockets for I/O and create timers.
 */
class EventLoop extends AbstractLoop
{
    /**
     * @var \EventBase
     */
    private $base;

    /**
     * Determines if the event extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('event');
    }
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     * @param   \EventBase|null $base Use null for an EventBase object to be automatically created.
     *
     * @throws  \Icicle\Loop\Exception\UnsupportedException If the event extension is not loaded.
     */
    public function __construct(EventFactoryInterface $eventFactory = null, EventBase $base = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedException(__CLASS__ . ' requires the event extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->base = $base ?: new EventBase();

        parent::__construct($eventFactory);
    }

    /**
     * @return  \EventBase
     *
     * @codeCoverageIgnore
     */
    protected function getEventBase()
    {
        return $this->base;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch($blocking)
    {
        $flags = EventBase::LOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EventBase::LOOP_NONBLOCK;
        }
        
        $this->base->loop($flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * Calls reInit() on the EventBase object.
     */
    public function reInit()
    {
        $this->base->reInit();
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new PollManager($factory, $this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new AwaitManager($factory, $this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return new TimerManager($factory, $this->base);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(EventFactoryInterface $factory)
    {
        return new SignalManager($this, $factory, $this->base);
    }
}
