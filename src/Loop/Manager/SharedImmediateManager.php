<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Loop\Manager;

use Icicle\Loop\Events\Immediate;
use Icicle\Loop\Loop;
use Icicle\Loop\Structures\ObjectStorage;

class SharedImmediateManager implements ImmediateManager
{
    /**
     * @var \Icicle\Loop\Loop
     */
    private $loop;

    /**
     * @var \SplQueue
     */
    private $queue;
    
    /**
     * @var \Icicle\Loop\Structures\ObjectStorage
     */
    private $immediates;
    
    /**
     * @param \Icicle\Loop\Loop $loop
     */
    public function __construct(Loop $loop)
    {
        $this->loop = $loop;
        $this->queue = new \SplQueue();
        $this->immediates = new ObjectStorage();
    }
    
    /**
     * {@inheritdoc}
     */
    public function create(callable $callback, array $args = [])
    {
        $immediate = new Immediate($this, $callback, $args);
        
        $this->execute($immediate);
        
        return $immediate;
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending(Immediate $immediate)
    {
        return $this->immediates->contains($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Immediate $immediate)
    {
        if (!$this->immediates->contains($immediate)) {
            $this->queue->push($immediate);
            $this->immediates->attach($immediate);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(Immediate $immediate)
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
    public function isEmpty()
    {
        return !$this->immediates->count();
    }

    /**
     * {@inheritdoc}
     */
    public function unreference(Immediate $immediate)
    {
        $this->immediates->unreference($immediate);
    }

    /**
     * {@inheritdoc}
     */
    public function reference(Immediate $immediate)
    {
        $this->immediates->reference($immediate);
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
    public function tick()
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
