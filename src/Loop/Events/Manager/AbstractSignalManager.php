<?php
namespace Icicle\Loop\Events\Manager;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SignalInterface;
use Icicle\Loop\Exception\InvalidSignalException;
use Icicle\Loop\LoopInterface;

abstract class AbstractSignalManager implements SignalManagerInterface
{
    /**
     * @var \Icicle\Loop\LoopInterface
     */
    private $loop;

    /**
     * @var \Icicle\Loop\Events\EventFactoryInterface
     */
    private $factory;

    /**
     * @var \SplObjectStorage[]
     */
    private $signals = [];

    /**
     * @param \Icicle\Loop\LoopInterface $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;

        foreach ($this->getSignalList() as $signo) {
            $this->signals[$signo] = new \SplObjectStorage();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function create($signo, callable $callback, array $args = null)
    {
        if (!isset($this->signals[$signo])) {
            throw new InvalidSignalException(sprintf('Invalid signal number: %d.', $signo));
        }

        $signal = $this->factory->signal($this, $signo, $callback);

        $this->signals[$signo]->attach($signal);

        return $signal;
    }

    public function enable(SignalInterface $signal)
    {
        $signo = $signal->getSignal();

        if (isset($this->signals[$signo]) && !$this->signals[$signo]->contains($signal)) {
            $this->signals[$signo]->attach($signal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disable(SignalInterface $signal)
    {
        $signo = $signal->getSignal();

        if (isset($this->signals[$signo]) && $this->signals[$signo]->contains($signal)) {
            $this->signals[$signo]->detach($signal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled(SignalInterface $signal)
    {
        $signo = $signal->getSignal();

        return isset($this->signals[$signo]) && $this->signals[$signo]->contains($signal);
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        foreach ($this->signals as $signo => $signals) {
            $this->signals[$signo] = new \SplObjectStorage();
        }
    }

    /**
     * Returns an array of signals to be handled. Exploits the fact that PHP will not notice the signal constants are
     * undefined if the pcntl extension is not installed.
     *
     * @return int[]
     */
    protected function getSignalList()
    {
        return [
            'SIGHUP'  => SIGHUP,
            'SIGINT'  => SIGINT,
            'SIGQUIT' => SIGQUIT,
            'SIGILL'  => SIGILL,
            'SIGABRT' => SIGABRT,
            'SIGTERM' => SIGTERM,
            'SIGCHLD' => SIGCHLD,
            'SIGCONT' => SIGCONT,
            'SIGTSTP' => SIGTSTP,
            'SIGPIPE' => SIGPIPE,
            'SIGUSR1' => SIGUSR1,
            'SIGUSR2' => SIGUSR2,
        ];
    }

    /**
     * Creates callback function for handling signals.
     *
     * @return callable
     */
    protected function createSignalCallback()
    {
        return function ($signo) {
            $handled = false;
            foreach ($this->signals[$signo] as $signal) {
                $handled = true;
                $signal->call();
            }

            switch ($signo) {
                case SIGHUP:
                case SIGINT:
                case SIGQUIT:
                    if (!$handled) {
                        $this->loop->stop();
                    }
                    break;

                case SIGTERM:
                    $this->loop->stop();
                    break;
            }
        };
    }
}
