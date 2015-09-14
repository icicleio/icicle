<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\EventFactoryInterface;
use Icicle\Loop\Events\SignalInterface;
use Icicle\Loop\Exception\InvalidSignalError;
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
     * @var \SplObjectStorage
     */
    private $referenced;

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

        $this->referenced = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function create($signo, callable $callback, array $args = [])
    {
        if (!isset($this->signals[$signo])) {
            throw new InvalidSignalError(sprintf('Invalid signal number: %d.', $signo));
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

        $this->referenced->detach($signal);
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
     * {@inheritdoc}
     */
    public function reference(SignalInterface $signal)
    {
        $signo = $signal->getSignal();

        if ($this->signals[$signo]->contains($signal)) {
            $this->referenced->attach($signal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(SignalInterface $signal)
    {
        $this->referenced->detach($signal);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty()
    {
        return !$this->referenced->count();
    }

    /**
     * Returns an array of signals to be handled. Exploits the fact that PHP will not notice the signal constants are
     * undefined if the pcntl extension is not installed.
     *
     * @return int[]
     */
    protected function getSignalList()
    {
        $signals = [
            SIGHUP,
            SIGINT,
            SIGQUIT,
            SIGILL,
            SIGABRT,
            SIGTRAP,
            SIGBUS,
            SIGTERM,
            SIGSEGV,
            SIGFPE,
            SIGALRM,
            SIGVTALRM,
            SIGPROF,
            SIGIO,
            SIGCONT,
            SIGURG,
            SIGPIPE,
            SIGXCPU,
            SIGXFSZ,
            SIGTTIN,
            SIGTTOU,
            SIGUSR1,
            SIGUSR2,
        ];

        if (defined('SIGIOT')) {
            $signals[] = SIGIOT;
        }

        if (defined('SIGSTKFLT')) {
            $signals[] = SIGSTKFLT;
        }

        if (defined('SIGCLD')) {
            $signals[] = SIGCLD;
        }

        if (defined('SIGCHLD')) {
            $signals[] = SIGCHLD;
        }

        return $signals;
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
                case SIGABRT:
                case SIGTRAP:
                case SIGXCPU:
                    if (!$handled) {
                        $this->loop->stop();
                    }
                    break;

                case SIGTERM:
                case SIGBUS:
                case SIGSEGV:
                case SIGFPE:
                    $this->loop->stop();
                    break;
            }
        };
    }

    /**
     * @return \Icicle\Loop\LoopInterface
     */
    protected function getLoop()
    {
        return $this->loop;
    }
}
