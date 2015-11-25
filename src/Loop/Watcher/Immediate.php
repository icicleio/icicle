<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Watcher;

use Icicle\Loop\Manager\ImmediateManager;

class Immediate implements Watcher
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
     * @var mixed[]|null
     */
    private $args;

    /**
     * @var bool
     */
    private $referenced = true;

    /**
     * @param \Icicle\Loop\Manager\ImmediateManager $manager
     * @param callable $callback Function called when the interval expires.
     * @param mixed[] $args Optional array of arguments to pass the callback function.
     */
    public function __construct(ImmediateManager $manager, callable $callback, array $args = [])
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->args = $args;
    }

    /**
     * @param callable $callback
     * @param mixed ...$args
     */
    public function setCallback(callable $callback /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);

        $this->callback = $callback;
        $this->args = $args;
    }
    
    /**
     * @internal
     *
     * Invokes the callback.
     */
    public function call()
    {
        if (empty($this->args)) {
            $callback = $this->callback;
            $callback();
        } else {
            call_user_func_array($this->callback, $this->args);
        }
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
