<?php
namespace Icicle\Loop;

use Exception;
use Icicle\Loop\Events\EventFactory;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\Manager\ImmediateManager;
use Icicle\Loop\Exception\RunningException;
use Icicle\Loop\Exception\SignalHandlingDisabledException;
use Icicle\Loop\Structures\CallableQueue;

/**
 * Abstract base class from which loop implementations may be derived. Loop implementations do not have to extend this
 * class, they only need to implement Icicle\Loop\LoopInterface.
 */
abstract class AbstractLoop implements LoopInterface
{
    const DEFAULT_MAX_DEPTH = 1000;

    /**
     * @var \Icicle\Loop\Structures\CallableQueue
     */
    private $callableQueue;
    
    /**
     * @var \Icicle\Loop\Events\Manager\SocketManagerInterface
     */
    private $pollManager;
    
    /**
     * @var \Icicle\Loop\Events\Manager\SocketManagerInterface
     */
    private $awaitManager;
    
    /**
     * @var \Icicle\Loop\Events\Manager\TimerManagerInterface
     */
    private $timerManager;
    
    /**
     * @var \Icicle\Loop\Events\Manager\ImmediateManagerInterface
     */
    private $immediateManager;

    /**
     * @var \Icicle\Loop\Events\Manager\SignalManagerInterface
     */
    private $signalManager;
    
    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $eventFactory;
    
    /**
     * @var bool
     */
    private $running = false;
    
    /**
     * Dispatches all pending I/O, timers, and signal callbacks.
     *
     * @param bool $blocking
     */
    abstract protected function dispatch($blocking);
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Events\Manager\SocketManagerInterface
     */
    abstract protected function createPollManager(EventFactoryInterface $eventFactory);
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Events\Manager\SocketManagerInterface
     */
    abstract protected function createAwaitManager(EventFactoryInterface $eventFactory);
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Events\Manager\TimerManagerInterface
     */
    abstract protected function createTimerManager(EventFactoryInterface $eventFactory);

    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Events\Manager\SignalManagerInterface
     */
    abstract protected function createSignalManager(EventFactoryInterface $eventFactory);

    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
     */
    public function __construct(EventFactoryInterface $eventFactory = null)
    {
        $this->eventFactory = $eventFactory;
        
        if (null === $this->eventFactory) {
            $this->eventFactory = $this->createEventFactory();
        }
        
        $this->callableQueue = new CallableQueue(self::DEFAULT_MAX_DEPTH);
        
        $this->immediateManager = $this->createImmediateManager($this->eventFactory);
        $this->timerManager = $this->createTimerManager($this->eventFactory);
        
        $this->pollManager = $this->createPollManager($this->eventFactory);
        $this->awaitManager = $this->createAwaitManager($this->eventFactory);
        
        if (extension_loaded('pcntl')) {
            $this->signalManager = $this->createSignalManager($this->eventFactory);
        }
    }
    
    /**
     * @return \Icicle\Loop\Events\EventFactoryInterface
     *
     * @codeCoverageIgnore
     */
    protected function getEventFactory()
    {
        return $this->eventFactory;
    }
    
    /**
     * @return \Icicle\Loop\Events\Manager\SocketManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getPollManager()
    {
        return $this->pollManager;
    }
    
    /**
     * @return \Icicle\Loop\Events\Manager\SocketManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getAwaitManager()
    {
        return $this->awaitManager;
    }
    
    /**
     * @return \Icicle\Loop\Events\Manager\TimerManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getTimerManager()
    {
        return $this->timerManager;
    }
    
    /**
     * @return \Icicle\Loop\Events\Manager\ImmediateManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getImmediateManager()
    {
        return $this->immediateManager;
    }

    /**
     * @return \Icicle\Loop\Events\Manager\SignalManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getSignalManager()
    {
        return $this->signalManager;
    }
    
    /**
     * Determines if there are any pending tasks in the loop.
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->pollManager->isEmpty()
            && $this->awaitManager->isEmpty()
            && $this->timerManager->isEmpty()
            && $this->callableQueue->isEmpty()
            && $this->immediateManager->isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick($blocking = true)
    {
        $blocking = $blocking && $this->callableQueue->isEmpty() && $this->immediateManager->isEmpty();
        
        // Dispatch all pending I/O, timers, and signal callbacks.
        $this->dispatch($blocking);
        
        $this->immediateManager->tick(); // Call the next immediate.
        
        $this->callableQueue->call(); // Call each callback in the tick queue (up to the max depth).
    }
    
    /**
     * {@inheritdoc}
     */
    public function run()
    {
        if ($this->isRunning()) {
            throw new RunningException('The loop was already running.');
        }
        
        $this->running = true;
        
        try {
            do {
                if ($this->isEmpty()) {
                    $this->stop();
                    return false;
                }
                $this->tick();
            } while ($this->isRunning());
        } catch (Exception $exception) {
            $this->stop();
            throw $exception;
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRunning()
    {
        return $this->running;
    }
    
    /**
     * {@inheritdoc}
     */
    public function stop()
    {
        $this->running = false;
    }
    
    /**
     * {@inheritdoc}
     */
    public function queue(callable $callback, array $args = null)
    {
        $this->callableQueue->insert($callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function maxQueueDepth($depth = null)
    {
        return $this->callableQueue->maxDepth($depth);
    }
    
    /**
     * {@inheritdoc}
     */
    public function poll($resource, callable $callback)
    {
        return $this->pollManager->create($resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function await($resource, callable $callback)
    {
        return $this->awaitManager->create($resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timer($interval, $periodic, callable $callback, array $args = null)
    {
        return $this->timerManager->create($interval, $periodic, $callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function immediate(callable $callback, array $args = null)
    {
        return $this->immediateManager->create($callback, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function signal($signo, callable $callback)
    {
        // @codeCoverageIgnoreStart
        if (null === $this->signalManager) {
            throw new SignalHandlingDisabledException(
                'The pcntl extension must be installed for signal constants to be defined.'
            );
        } // @codeCoverageIgnoreEnd

        return $this->signalManager->create($signo, $callback);
    }
    
    /**
     * @return bool
     */
    public function signalHandlingEnabled()
    {
        return null !== $this->signalManager;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->callableQueue->clear();
        $this->immediateManager->clear();
        $this->pollManager->clear();
        $this->awaitManager->clear();
        $this->timerManager->clear();

        if (null !== $this->signalManager) {
            $this->signalManager->clear();
        }
    }
    
    /**
     * @return \Icicle\Loop\Events\EventFactoryInterface
     */
    protected function createEventFactory()
    {
        return new EventFactory();
    }
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     *
     * @return \Icicle\Loop\Events\Manager\ImmediateManagerInterface
     */
    protected function createImmediateManager(EventFactoryInterface $factory)
    {
        return new ImmediateManager($factory);
    }
}
