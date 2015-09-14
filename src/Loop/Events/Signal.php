<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\SignalManagerInterface;

class Signal implements SignalInterface
{
    /**
     * @var \Icicle\Loop\Manager\SignalManagerInterface
     */
    private $manager;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @var int
     */
    private $signo;

    /**
     * @var bool
     */
    private $referenced = false;

    /**
     * @param \Icicle\Loop\Manager\SignalManagerInterface $manager
     * @param int $signo
     * @param callable $callback
     */
    public function __construct(SignalManagerInterface $manager, $signo, callable $callback)
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->signo = (int) $signo;
    }

    /**
     * {@inheritdoc}
     */
    public function call()
    {
        $callback = $this->callback;
        $callback($this->signo);
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke()
    {
        $this->call();
    }

    /**
     * {@inheritdoc}
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * {@inheritdoc}
     */
    public function enable()
    {
        $this->manager->enable($this);

        if ($this->referenced) {
            $this->manager->reference($this);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disable()
    {
        $this->manager->disable($this);
    }

    /**
     * {@inheritdoc}
     */
    public function isEnabled()
    {
        return $this->manager->isEnabled($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getSignal()
    {
        return $this->signo;
    }

    /**
     * {@inheritdoc}
     */
    public function unreference()
    {
        $this->referenced = false;
        $this->manager->unreference($this);
    }

    /**
     * {@inheritdoc}
     */
    public function reference()
    {
        $this->referenced = true;
        $this->manager->reference($this);
    }
}
