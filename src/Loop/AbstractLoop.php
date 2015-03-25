<?php
namespace Icicle\Loop;

use Exception;
use Icicle\EventEmitter\EventEmitterTrait;
use Icicle\Loop\Events\EventFactory;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Exception\RunningException;
use Icicle\Loop\Exception\SignalHandlingDisabledException;
use Icicle\Loop\Manager\ImmediateManager;
use Icicle\Loop\Manager\SocketManagerInterface;
use Icicle\Loop\Manager\TimerManagerInterface;
use Icicle\Loop\Structures\CallableQueue;

/**
 * Abstract base class from which loop implementations may be derived. Loop implementations do not have to extend this
 * class, they only need to implement Icicle\Loop\LoopInterface.
 */
abstract class AbstractLoop implements LoopInterface
{
    use EventEmitterTrait;
    
    const DEFAULT_MAX_DEPTH = 1000;
    
    /**
     * @var bool
     */
    private $signalHandlingEnabled;
    
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
     * @param   \Icicle\Loop\Manager\SocketManagerInterface $pollManager
     * @param   \Icicle\Loop\Manager\SocketManagerInterface $awaitManager
     * @param   \Icicle\Loop\Manager\TimerManagerInterface $timerManager
     * @param   bool $blocking
     */
    abstract protected function dispatch(
        SocketManagerInterface $pollManager,
        SocketManagerInterface $awaitManager,
        TimerManagerInterface $timerManager,
        $blocking
    );
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return  \Icicle\Loop\Manager\SocketManagerInterface
     */
    abstract protected function createPollManager(EventFactoryInterface $eventFactory);
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return  \Icicle\Loop\Manager\SocketManagerInterface
     */
    abstract protected function createAwaitManager(EventFactoryInterface $eventFactory);
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface
     *
     * @return  \Icicle\Loop\Manager\TimerManagerInterface
     */
    abstract protected function createTimerManager(EventFactoryInterface $eventFactory);
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface|null $eventFactory
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
        
        $this->signalHandlingEnabled = extension_loaded('pcntl');
    }
    
    /**
     * @return  \Icicle\Loop\Events\EventFactoryInterface
     *
     * @codeCoverageIgnore
     */
    protected function getEventFactory()
    {
        return $this->eventFactory;
    }
    
    /**
     * @return  \Icicle\Loop\Manager\SocketManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getPollManager()
    {
        return $this->pollManager;
    }
    
    /**
     * @return  \Icicle\Loop\Manager\SocketManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getAwaitManager()
    {
        return $this->awaitManager;
    }
    
    /**
     * @return  \Icicle\Loop\Manager\TimerManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getTimerManager()
    {
        return $this->timerManager;
    }
    
    /**
     * @return  \Icicle\Loop\Manager\ImmediateManagerInterface
     *
     * @codeCoverageIgnore
     */
    protected function getImmediateManager()
    {
        return $this->immediateManager;
    }
    
    /**
     * Determines if there are any pending tasks in the loop.
     *
     * @return  bool
     */
    public function isEmpty()
    {
        return $this->pollManager->isEmpty() &&
            $this->awaitManager->isEmpty() &&
            $this->timerManager->isEmpty() &&
            $this->callableQueue->isEmpty() &&
            $this->immediateManager->isEmpty();
    }
    
    /**
     * @inheritdoc
     */
    public function tick($blocking = true)
    {
        $blocking = $blocking && $this->callableQueue->isEmpty() && $this->immediateManager->isEmpty();
        
        // Dispatch all pending I/O, timers, and signal callbacks.
        $this->dispatch($this->pollManager, $this->awaitManager, $this->timerManager, $blocking);
        
        $this->immediateManager->tick(); // Call the next immediate.
        
        $this->callableQueue->call(); // Call each callback in the tick queue (up to the max depth).
    }
    
    /**
     * @inheritdoc
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
     * @inheritdoc
     */
    public function isRunning()
    {
        return $this->running;
    }
    
    /**
     * @inheritdoc
     */
    public function stop()
    {
        $this->running = false;
    }
    
    /**
     * @inheritdoc
     */
    public function schedule(callable $callback, array $args = null)
    {
        $this->callableQueue->insert($callback, $args);
    }
    
    /**
     * @inheritdoc
     */
    public function maxScheduleDepth($depth = null)
    {
        return $this->callableQueue->maxDepth($depth);
    }
    
    /**
     * @inheritdoc
     */
    public function poll($resource, callable $callback)
    {
        return $this->pollManager->create($resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function await($resource, callable $callback)
    {
        return $this->awaitManager->create($resource, $callback);
    }
    
    /**
     * @inheritdoc
     */
    public function timer(callable $callback, $interval, $periodic = false, array $args = null)
    {
        return $this->timerManager->create($callback, $interval, $periodic, $args);
    }
    
    /**
     * @inheritdoc
     */
    public function immediate(callable $callback, array $args = null)
    {
        return $this->immediateManager->create($callback, $args);
    }
    
    /**
     * @return  bool
     */
    public function signalHandlingEnabled()
    {
        return $this->signalHandlingEnabled;
    }
    
    /**
     * Returns an array of signals to be handled. Exploits the fact that PHP will not notice the signal constants are
     * undefined if the pcntl extension is not installed.
     *
     * @return  int[]
     *
     * @throws  \Icicle\Loop\Exception\SignalHandlingDisabledException
     */
    public function getSignalList()
    {
        // @codeCoverageIgnoreStart
        if (!$this->signalHandlingEnabled()) {
            throw new SignalHandlingDisabledException('The pcntl extension must be installed for signal constants to be defined.');
        } // @codeCoverageIgnoreEnd
        
        return [
            'SIGHUP' => SIGHUP,
            'SIGINT' => SIGINT,
            'SIGQUIT' => SIGQUIT,
            'SIGILL' => SIGILL,
            'SIGABRT' => SIGABRT,
            'SIGTERM' => SIGTERM,
            'SIGCHLD' => SIGCHLD,
            'SIGCONT' => SIGCONT,
            'SIGTSTP' => SIGTSTP,
            'SIGPIPE' => SIGPIPE,
            'SIGUSR1' => SIGUSR1,
            'SIGUSR2' => SIGUSR2
        ];
    }
    
    /**
     * Creates callback function for handling signals.
     *
     * @return  callable function (int $signo)
     *
     * @throws  \Icicle\Loop\Exception\SignalHandlingDisabledException
     */
    protected function createSignalCallback()
    {
        // @codeCoverageIgnoreStart
        if (!$this->signalHandlingEnabled()) {
            throw new SignalHandlingDisabledException('The pcntl extension must be installed for signal constants to be defined.');
        } // @codeCoverageIgnoreEnd
        
        return function ($signo) {
            switch ($signo)
            {
                case SIGHUP:
                case SIGINT:
                case SIGQUIT:
                    if (!$this->emit($signo, $signo)) {
                        $this->stop();
                    }
                    break;
                    
                case SIGTERM:
                    $this->emit($signo, $signo);
                    $this->stop();
                    break;
                    
                case SIGCHLD:
                    while (0 < ($pid = pcntl_wait($status, WNOHANG))) {
                        $this->emit($signo, $signo, $pid, $status);
                    }
                    break;
                    
                default:
                    $this->emit($signo, $signo);
            }
        };
    }
    
    /**
     * @inheritdoc
     */
    public function clear()
    {
        $this->removeAllListeners();
        
        $this->callableQueue->clear();
        $this->immediateManager->clear();
        $this->pollManager->clear();
        $this->awaitManager->clear();
        $this->timerManager->clear();
    }
    
    /**
     * @return  \Icicle\Loop\Events\EventFactoryInterface
     */
    protected function createEventFactory()
    {
        return new EventFactory();
    }
    
    /**
     * @param   \Icicle\Loop\Events\EventFactoryInterface $factory
     *
     * @return  \Icicle\Loop\Manager\ImmediateManagerInterface
     */
    protected function createImmediateManager(EventFactoryInterface $factory)
    {
        return new ImmediateManager($factory);
    }
}
