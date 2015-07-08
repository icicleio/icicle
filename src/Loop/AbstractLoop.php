<?php
namespace Icicle\Loop;

use Icicle\Loop\Events\EventFactory;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\ImmediateInterface;
use Icicle\Loop\Events\SignalInterface;
use Icicle\Loop\Events\SocketEventInterface;
use Icicle\Loop\Events\TimerInterface;
use Icicle\Loop\Exception\RunningError;
use Icicle\Loop\Exception\SignalHandlingDisabledError;
use Icicle\Loop\Manager\ImmediateManager;
use Icicle\Loop\Manager\ImmediateManagerInterface;
use Icicle\Loop\Manager\SignalManagerInterface;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;
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
     * @var \Icicle\Loop\Manager\SocketManagerInterface
     */
    private $pollManager;
    
    /**
     * @var \Icicle\Loop\Manager\SocketManagerInterface
     */
    private $awaitManager;
    
    /**
     * @var \Icicle\Loop\Manager\TimerManagerInterface
     */
    private $timerManager;
    
    /**
     * @var \Icicle\Loop\Manager\ImmediateManagerInterface
     */
    private $immediateManager;

    /**
     * @var \Icicle\Loop\Manager\SignalManagerInterface
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
    abstract protected function dispatch(bool $blocking);
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Manager\SocketManagerInterface
     */
    abstract protected function createPollManager(EventFactoryInterface $eventFactory): SocketManagerInterface;
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Manager\SocketManagerInterface
     */
    abstract protected function createAwaitManager(EventFactoryInterface $eventFactory): SocketManagerInterface;
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Manager\TimerManagerInterface
     */
    abstract protected function createTimerManager(EventFactoryInterface $eventFactory): TimerManagerInterface;

    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return \Icicle\Loop\Manager\SignalManagerInterface
     */
    abstract protected function createSignalManager(EventFactoryInterface $eventFactory): SignalManagerInterface;

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
    protected function getEventFactory(): EventFactoryInterface
    {
        return $this->eventFactory;
    }
    
    /**
     * @return \Icicle\Loop\Manager\SocketManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getPollManager(): SocketManagerInterface
    {
        return $this->pollManager;
    }
    
    /**
     * @return \Icicle\Loop\Manager\SocketManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getAwaitManager(): SocketManagerInterface
    {
        return $this->awaitManager;
    }
    
    /**
     * @return \Icicle\Loop\Manager\TimerManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getTimerManager(): TimerManagerInterface
    {
        return $this->timerManager;
    }
    
    /**
     * @return \Icicle\Loop\Manager\ImmediateManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getImmediateManager(): ImmediateManagerInterface
    {
        return $this->immediateManager;
    }

    /**
     * @return \Icicle\Loop\Manager\SignalManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getSignalManager(): SignalManagerInterface
    {
        return $this->signalManager;
    }
    
    /**
     * Determines if there are any pending tasks in the loop.
     *
     * @return bool
     */
    public function isEmpty(): bool
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
    public function tick(bool $blocking = true)
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
    public function run(): bool
    {
        if ($this->isRunning()) {
            throw new RunningError('The loop was already running.');
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
        } catch (\Throwable $exception) {
            $this->stop();
            throw $exception;
        }
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRunning(): bool
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
    public function maxQueueDepth(int $depth = null): int
    {
        return $this->callableQueue->maxDepth($depth);
    }
    
    /**
     * {@inheritdoc}
     */
    public function poll($resource, callable $callback): SocketEventInterface
    {
        return $this->pollManager->create($resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function await($resource, callable $callback): SocketEventInterface
    {
        return $this->awaitManager->create($resource, $callback);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timer(float $interval, bool $periodic, callable $callback, array $args = null): TimerInterface
    {
        return $this->timerManager->create($interval, $periodic, $callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function immediate(callable $callback, array $args = null): ImmediateInterface
    {
        return $this->immediateManager->create($callback, $args);
    }

    /**
     * {@inheritdoc}
     */
    public function signal(int $signo, callable $callback): SignalInterface
    {
        // @codeCoverageIgnoreStart
        if (null === $this->signalManager) {
            throw new SignalHandlingDisabledError(
                'The pcntl extension must be installed for signal constants to be defined.'
            );
        } // @codeCoverageIgnoreEnd

        return $this->signalManager->create($signo, $callback);
    }
    
    /**
     * @return bool
     */
    public function signalHandlingEnabled(): bool
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
    protected function createEventFactory(): EventFactoryInterface
    {
        return new EventFactory();
    }
    
    /**
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     *
     * @return \Icicle\Loop\Manager\ImmediateManagerInterface
     */
    protected function createImmediateManager(EventFactoryInterface $factory): ImmediateManagerInterface
    {
        return new ImmediateManager($factory);
    }
}
