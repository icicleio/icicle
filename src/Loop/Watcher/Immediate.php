<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

use Icicle\Loop\Manager\ImmediateManager;

class Immediate extends Watcher
{
    /**
     * @var \Icicle\Loop\Manager\ImmediateManager
     */
    private $manager;
    
    /**
     * @var callable
     */
    private $callback;

    /**
     * @var bool
     */
    private $referenced = true;

    /**
     * @param \Icicle\Loop\Manager\ImmediateManager $manager
     * @param callable $callback Function called when the interval expires.
     * @param mixed $data Optional data to associate with the watcher.
     */
    public function __construct(ImmediateManager $manager, callable $callback, $data = null)
    {
        $this->manager = $manager;
        $this->callback = $callback;

        if (null !== $data) {
            $this->setData($data);
        }
    }

    /**
     * @param callable $callback
     */
    public function setCallback(callable $callback)
    {
        $this->callback = $callback;
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function call()
    {
        $callback = $this->callback;
        $callback($this);
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function __invoke()
    {
        $this->call();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->manager->isPending($this);
    }

    /**
     * Execute the immediate if not pending.
     */
    public function execute()
    {
        $this->manager->execute($this);

        if (!$this->referenced) {
            $this->manager->unreference($this);
        }
    }

    /**
     * Cancels the immediate if pending.
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }

    /**
     * An unreferenced event will allow the event loop to exit if no other watchers are pending.
     */
    public function unreference()
    {
        $this->referenced = false;
        $this->manager->unreference($this);
    }

    /**
     * Adds a reference to the event, causing the event loop to continue to run as long as the watcher is still pending.
     */
    public function reference()
    {
        $this->referenced = true;
        $this->manager->reference($this);
    }
}
