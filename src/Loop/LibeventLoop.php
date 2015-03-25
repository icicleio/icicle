<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\UnsupportedException;
use Icicle\Loop\Manager\AwaitManagerInterface;
use Icicle\Loop\Manager\Libevent\AwaitManager;
use Icicle\Loop\Manager\Libevent\PollManager;
use Icicle\Loop\Manager\Libevent\TimerManager;
use Icicle\Loop\Manager\PollManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;

class LibeventLoop extends AbstractLoop
{
    /**
     * Event base created with event_base_new().
     *
     * @var resource
     */
    private $base;
    
    /**
     * @var resource[int]
     */
    private $signalEvents = [];
    
    /**
     * Determines if the libevent extension is loaded, which is required for this class.
     *
     * @return  bool
     */
    public static function enabled()
    {
        return extension_loaded('libevent');
    }
    
    /**
     * @param   EventFactoryInterface|null $eventFactory
     * @param   resource|null Resource created by event_base_new() or null to automatically create an event base.
     *
     * @throws  UnsupportedException If the libevent extension is not loaded.
     */
    public function __construct(EventFactoryInterface $eventFactory = null, $base = null)
    {
        // @codeCoverageIgnoreStart
        if (!self::enabled()) {
            throw new UnsupportedException(__CLASS__ . ' requires the libevent extension.');
        } // @codeCoverageIgnoreEnd
        
        $this->base = $base;
        
        // @codeCoverageIgnoreStart
        if (!is_resource($this->base)) {
            $this->base = event_base_new();
        } // @codeCoverageIgnoreEnd
        
        parent::__construct($eventFactory);
        
        if ($this->signalHandlingEnabled()) {
            $callback = $this->createSignalCallback();
            
            foreach ($this->getSignalList() as $signal) {
                $this->createEvent($signal);
                $event = event_new();
                event_set($event, $signal, EV_SIGNAL | EV_PERSIST, $callback);
                event_base_set($event, $this->base);
                event_add($event);
                $this->signalEvents[$signal] = $event;
            }
        }
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        foreach ($this->signalEvents as $event) {
            event_free($event);
        }
    }
    
    /**
     * @return  resource
     *
     * @codeCoverageIgnore
     */
    protected function getEventBase()
    {
        return $this->base;
    }
    
    /**
     * @inheritdoc
     */
    public function reInit()
    {
        event_base_reinit($this->base);
    }
    
    /**
     * @inheritdoc
     */
    protected function dispatch(
        PollManagerInterface $pollManager,
        AwaitManagerInterface $awaitManager,
        TimerManagerInterface $timerManager,
        $blocking
    ) {
        $flags = EVLOOP_ONCE;
        
        if (!$blocking) {
            $flags |= EVLOOP_NONBLOCK;
        }
        
        event_base_loop($this->base, $flags); // Dispatch I/O, timer, and signal callbacks.
    }
    
    /**
     * @inheritdoc
     */
    protected function createPollManager(EventFactoryInterface $factory)
    {
        return new PollManager($factory, $this->base);
    }
    
    /**
     * @inheritdoc
     */
    protected function createAwaitManager(EventFactoryInterface $factory)
    {
        return new AwaitManager($factory, $this->base);
    }
    
    /**
     * @inheritdoc
     */
    protected function createTimerManager(EventFactoryInterface $factory)
    {
        return new TimerManager($factory, $this->base);
    }
}
