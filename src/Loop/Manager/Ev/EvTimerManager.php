<?php
namespace Icicle\Loop\Manager\Ev;

use Icicle\Loop\EvLoop;
use Icicle\Loop\Manager\TimerManager;
use Icicle\Loop\Structures\ObjectStorage;
use Icicle\Loop\Watcher\Timer;

class EvTimerManager implements TimerManager
{
    /**
     * @var \EvLoop
     */
    private $loop;

    /**
     * ObjectStorage mapping Timer objects to EvTimer objects.
     *
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $timers;
    
    /**
     * @var callable
     */
    private $callback;
    
    /**
     * @param   \Icicle\Loop\EvLoop $loop
     */
    public function __construct(EvLoop $loop)
    {
        $this->loop = $loop->getEvLoop();
        
        $this->timers = new ObjectStorage();
        
        $this->callback = function (\EvTimer $event) {
            /** @var \Icicle\Loop\Watcher\Timer $timer */
            $timer = $event->data;

            if (!$timer->isPeriodic()) {
                $event->stop();
                unset($this->timers[$timer]);
            } else {
                $event->again();
            }

            $timer->call();
        };
    }
    
    /**
     * @codeCoverageIgnore
     */
    public function __destruct()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->stop();
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return !$this->timers->count();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create($interval, $periodic = false, callable $callback, $data = null)
    {
        $timer = new Timer($this, $interval, $periodic, $callback, $data);

        $this->start($timer);

        return $timer;
    }

    /**
     * {@inheritdoc}
     */
    public function start(Timer $timer)
    {
        if (!isset($this->timers[$timer])) {
            $interval = $timer->getInterval();

            $event = $this->loop->timer($interval, $timer->isPeriodic() ? $interval : 0, $this->callback, $timer);

            $this->timers[$timer] = $event;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function stop(Timer $timer)
    {
        if (isset($this->timers[$timer])) {
            $this->timers[$timer]->stop();
            unset($this->timers[$timer]);
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Timer $timer)
    {
        return isset($this->timers[$timer]) && $this->timers[$timer]->is_active;
    }
    
    /**
     * {@inheritdoc}
     */
    public function unreference(Timer $timer)
    {
        $this->timers->unreference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function reference(Timer $timer)
    {
        $this->timers->reference($timer);
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        for ($this->timers->rewind(); $this->timers->valid(); $this->timers->next()) {
            $this->timers->getInfo()->stop();
        }
        
        $this->timers = new ObjectStorage();
    }
}