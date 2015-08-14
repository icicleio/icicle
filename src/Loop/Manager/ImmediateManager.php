<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\{EventFactoryInterface, ImmediateInterface};
use Icicle\Loop\LoopInterface;

class ImmediateManager implements ImmediateManagerInterface
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
     * @var \SplQueue
     */
    private $queue;
    
    /**
     * @var \SplObjectStorage
     */
    private $immediates;
    
    /**
     * @param \Icicle\Loop\LoopInterface $loop
     * @param \Icicle\Loop\Events\EventFactoryInterface $factory
     */
    public function __construct(LoopInterface $loop, EventFactoryInterface $factory)
    {
        $this->loop = $loop;
        $this->factory = $factory;
        $this->queue = new \SplQueue();
        $this->immediates = new \SplObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create(callable $callback, array $args = []): ImmediateInterface
    {
        $immediate = $this->factory->immediate($this, $callback, $args);
        
        $this->execute($immediate);
        
        return $immediate;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(ImmediateInterface $immediate): bool
    {
        return $this->immediates->contains($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(ImmediateInterface $immediate)
    {
        if (!$this->immediates->contains($immediate)) {
            $this->queue->push($immediate);
            $this->immediates->attach($immediate);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(ImmediateInterface $immediate)
    {
        if ($this->immediates->contains($immediate)) {
            $this->immediates->detach($immediate);

            foreach ($this->queue as $key => $event) {
                if ($event === $immediate) {
                    unset($this->queue[$key]);
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return 0 === $this->immediates->count();
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $this->queue = new \SplQueue();
        $this->immediates = new \SplObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function tick(): bool
    {
        if (!$this->queue->isEmpty()) {
            $immediate = $this->queue->shift();

            $this->immediates->detach($immediate);

            // Execute the immediate.
            $immediate->call();

            return true;
        }

        return false;
    }
}
