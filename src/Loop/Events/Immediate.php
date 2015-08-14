<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Events;

use Icicle\Loop\Manager\ImmediateManagerInterface;

class Immediate implements ImmediateInterface
{
    /**
     * @var \Icicle\Loop\Manager\ImmediateManagerInterface
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
     * @param \Icicle\Loop\Manager\ImmediateManagerInterface $manager
     * @param callable $callback Function called when the interval expires.
     * @param mixed[] $args Optional array of arguments to pass the callback function.
     */
    public function __construct(ImmediateManagerInterface $manager, callable $callback, array $args = [])
    {
        $this->manager = $manager;
        $this->callback = $callback;
        $this->args = $args;
    }
    
    /**
     * {@inheritdoc}
     */
    public function call()
    {
        if (empty($this->args)) {
            ($this->callback)();
        } else {
            ($this->callback)(...$this->args);
        }
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
    public function isPending(): bool
    {
        return $this->manager->isPending($this);
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->manager->execute($this);
    }

    /**
     * {@inheritdoc}
     */
    public function cancel()
    {
        $this->manager->cancel($this);
    }
}
