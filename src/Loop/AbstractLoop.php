<?php
namespace Icicle\Loop;

use Exception;
use Icicle\EventEmitter\EventEmitterTrait;
use Icicle\Loop\Events\EventFactory;
use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\ImmediateInterface;
use Icicle\Loop\Exception\RunningException;
use Icicle\Loop\Exception\SignalHandlingDisabledException;
use Icicle\Loop\Structures\ImmediateQueue;
use Icicle\Structures\CallableQueue;

abstract class AbstractLoop implements LoopInterface
{
    use EventEmitterTrait;
    
    const DEFAULT_MAX_DEPTH = 1000;
    
    /**
     * @var bool
     */
    private $signalHandlingEnabled;
    
    /**
     * @var CallableQueue
     */
    private $callableQueue;
    
    /**
     * @var ImmediateQueue
     */
    private $immediateQueue;
    
    /**
     * @var EventFactoryInterface
     */
    private $eventFactory;
    
    /**
     * @var bool
     */
    private $running = false;
    
    /**
     * Dispatches all pending I/O, timers, and signal callbacks.
     *
     * @param   bool $blocking
     */
    abstract protected function dispatch($blocking);
    
    /**
     * @param   EventFactoryInterface|null $eventFactory
     */
    public function __construct(EventFactoryInterface $eventFactory = null)
    {
        $this->callableQueue = new CallableQueue(self::DEFAULT_MAX_DEPTH);
        $this->immediateQueue = new ImmediateQueue();
        $this->signalHandlingEnabled = extension_loaded('pcntl');
        $this->eventFactory = $eventFactory;
        
        if (null === $this->eventFactory) {
            $this->eventFactory = new EventFactory();
        }
    }
    
    /**
     * @return  EventFactoryInterface
     */
    protected function getEventFactory()
    {
        return $this->eventFactory;
    }
    
    /**
     * Determines if there are any pending tasks in the loop.
     *
     * @return  bool
     */
    public function isEmpty()
    {
        return $this->callableQueue->isEmpty() && $this->immediateQueue->isEmpty();
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick($blocking = true)
    {
        // Dispatch all pending I/O, timers, and signal callbacks.
        $this->dispatch($blocking && $this->callableQueue->isEmpty() && $this->immediateQueue->isEmpty());
        
        $this->immediateQueue->tick(); // Call the next immediate.
        
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
    public function schedule(callable $callback, array $args = null)
    {
        $this->callableQueue->insert($callback, $args);
    }
    
    /**
     * {@inheritdoc}
     */
    public function maxScheduleDepth($depth = null)
    {
        return $this->callableQueue->maxDepth($depth);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createImmediate(callable $callback, array $args = null)
    {
        $immediate = $this->getEventFactory()->createImmediate($this, $callback, $args);
        
        $this->immediateQueue->add($immediate);
        
        return $immediate;
    }
    
    /**
     * {@inheritdoc}
     */
    public function cancelImmediate(ImmediateInterface $immediate)
    {
        $this->immediateQueue->remove($immediate);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isImmediatePending(ImmediateInterface $immediate)
    {
        return $this->immediateQueue->contains($immediate);
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
     * @return  int[string]
     *
     * @throws  SignalHandlingDisabledException
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
     * @throws  SignalHandlingDisabledException
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
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->removeAllListeners();
        
        $this->callableQueue->clear();
        $this->immediateQueue->clear();
    }
}
