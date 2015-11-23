<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine;

use Exception;
use Generator;
use Icicle\Awaitable\Future;
use Icicle\Awaitable\Awaitable;
use Icicle\Loop;

/**
 * This class implements cooperative coroutines using Generators. Coroutines should yield promises to pause execution
 * of the coroutine until the promise has resolved. If the promise is fulfilled, the fulfillment value is sent to the
 * generator. If the promise is rejected, the rejection exception is thrown into the generator.
 */
final class Coroutine extends Future
{
    /**
     * @var \Generator|null
     */
    private $generator;

    /**
     * @var \Closure|null
     */
    private $send;
    
    /**
     * @var \Closure|null
     */
    private $capture;
    
    /**
     * @var mixed[]|null
     */
    private $next;

    /**
     * @var bool
     */
    private $paused = false;

    /**
     * @param \Generator $generator
     */
    public function __construct(Generator $generator)
    {
        parent::__construct();

        $this->generator = $generator;

        /**
         * @param mixed $value The value to send to the generator.
         */
        $this->send = function ($value = null) {
            if ($this->paused) { // If paused, save callable and value for resuming.
                $this->next = [$this->send, $value];
                return;
            }

            try {
                // Send the new value and execute to next yield statement.
                $this->next($this->generator->send($value), $value);
            } catch (Exception $exception) {
                $this->reject($exception);
                $this->close();
            }
        };

        /**
         * @param \Exception $exception Exception to be thrown into the generator.
         */
        $this->capture = function (Exception $exception) {
            if ($this->paused) { // If paused, save callable and exception for resuming.
                $this->next = [$this->capture, $exception];
                return;
            }

            try {
                // Throw exception at current execution point.
                $this->next($this->generator->throw($exception));
            } catch (Exception $exception) {
                $this->reject($exception);
                $this->close();
            }
        };

        try {
            $this->next($this->generator->current());
        } catch (Exception $exception) {
            $this->reject($exception);
            $this->close();
        }
    }

    /**
     * Examines the value yielded from the generator and prepares the next step in interation.
     *
     * @param mixed $yielded
     * @param mixed $last
     */
    private function next($yielded, $last = null)
    {
        if (!$this->generator->valid()) {
            $this->resolve($last);
            $this->close();
            return;
        }

        if ($yielded instanceof Generator) {
            $yielded = new self($yielded);
        }

        if ($yielded instanceof Awaitable) {
            $yielded->done($this->send, $this->capture);
        } else {
            Loop\queue($this->send, $yielded);
        }
    }

    /**
     * The garbage collector does not automatically detect (at least not quickly) the circular references that can be
     * created, so explicitly setting these parameters to null is necessary for proper freeing of memory.
     */
    private function close()
    {
        $this->next = null;
        $this->send = null;
        $this->capture = null;

        $this->paused = true;

        $this->generator = null;
    }

    /**
     * {@inheritdoc}
     */
    public function pause()
    {
        $this->paused = true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function resume()
    {
        if ($this->isPending() && $this->paused) {
            $this->paused = false;
            
            if (null !== $this->next) {
                list($callable, $value) = $this->next;
                Loop\queue($callable, $value);
                $this->next = null;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPaused()
    {
        return $this->paused;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $reason = null)
    {
        if (null !== $this->generator) {
            $current = $this->generator->current(); // Get last yielded value.
            if ($current instanceof Awaitable) {
                $current->cancel($reason); // Cancel last yielded awaitable.
            }

            try {
                $this->close(); // Throwing finally blocks in the Generator may cause close() to throw.
            } catch (Exception $exception) {
                $reason = $exception;
            }
        }

        parent::cancel($reason);
    }
}
