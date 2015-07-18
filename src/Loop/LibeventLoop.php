<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\UnsupportedError;
use Icicle\Loop\Manager\Libevent\{AwaitManager, PollManager, SignalManager, TimerManager};
use Icicle\Loop\Manager\{SignalManagerInterface, SocketManagerInterface, TimerManagerInterface};

/**
 * Uses the libevent extension to poll sockets for I/O and create timers.
 */
class LibeventLoop extends AbstractLoop
{
    /**
     * Event base created with event_base_new().
     *
     * @var resource
     */
    private $base;

    /**
     * Determines if the libevent extension is loaded, which is required for this class.
     *
     * @return bool
     */
    public static function enabled(): bool
    {
        return extension_loaded('libevent');
    }
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     * @param resource|null Resource created by event_base_new() or null to automatically create an event base.
     *
     * @throws \Icicle\Loop\Exception\UnsupportedError If the libevent extension is not loaded.
     */
    public function __construct(EventFactoryInterface $eventFactory = null, $base = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedError(__CLASS__ . ' requires the libevent extension.');
        } // @codeCoverageIgnoreEnd

        // @codeCoverageIgnoreStart
        if (!is_resource($base)) {
            $this->base = event_base_new();
        } else { // @codeCoverageIgnoreEnd
            $this->base = $base;
        }
        
        parent::__construct($eventFactory);
    }

    /**
     * @return resource
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
    public function reInit()
    {
        event_base_reinit($this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function dispatch(bool $blocking)
    {
        $flags = EVLOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EVLOOP_NONBLOCK;
        }
        
        event_base_loop($this->base, $flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createPollManager(EventFactoryInterface $factory): SocketManagerInterface
    {
        return new PollManager($factory, $this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createAwaitManager(EventFactoryInterface $factory): SocketManagerInterface
    {
        return new AwaitManager($factory, $this->base);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function createTimerManager(EventFactoryInterface $factory): TimerManagerInterface
    {
        return new TimerManager($factory, $this->base);
    }

    /**
     * {@inheritdoc}
     */
    protected function createSignalManager(EventFactoryInterface $factory): SignalManagerInterface
    {
        return new SignalManager($this, $factory, $this->base);
    }
}
