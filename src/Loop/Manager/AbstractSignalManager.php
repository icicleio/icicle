<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\EventFactory;
use Icicle\Loop\Events\Signal;
use Icicle\Loop\Exception\InvalidSignalError;
use Icicle\Loop\Loop;

abstract class AbstractSignalManager implements SignalManager
{
    /**
     * @var \Icicle\Loop\Loop
     */
    private $loop;

    /**
     * @var \SplObjectStorage[]
     */
    private $signals = [];

    /**
     * @var \SplObjectStorage
     */
    private $referenced;

    /**
     * @param \Icicle\Loop\Loop $loop
     */
    public function __construct(Loop $loop)
    {
        $this->loop = $loop;

        foreach ($this->getSignalList() as $signo) {
            $this->signals[$signo] = new \SplObjectStorage();
        }

        $this->referenced = new \SplObjectStorage();
    }

    /**
     * {@inheritdoc}
     */
    public function create($signo, callable $callback)
    {
        if (!isset($this->signals[$signo])) {
            throw new InvalidSignalError($signo);
        }

        $signal = new Signal($this, $signo, $callback);

        $this->signals[$signo]->attach($signal);

        return $signal;
    }

    public function enable(Signal $signal)
    {
        $signo = $signal->getSignal();

        if (isset($this->signals[$signo]) && !$this->signals[$signo]->contains($signal)) {
            $this->signals[$signo]->attach($signal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disable(Signal $signal)
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
    public function isEnabled(Signal $signal)
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
    public function reference(Signal $signal)
    {
        $signo = $signal->getSignal();

        if ($this->signals[$signo]->contains($signal)) {
            $this->referenced->attach($signal);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(Signal $signal)
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
     * @return \Icicle\Loop\Loop
     */
    protected function getLoop()
    {
        return $this->loop;
    }
}
