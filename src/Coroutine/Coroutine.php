<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using coroutines built with awaitables.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license MIT See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine;

use Generator;
use Icicle\Awaitable\{Awaitable, Future};
use Icicle\Loop;
use Throwable;

/**
 * This class implements cooperative coroutines using Generators. Coroutines should yield awaitables to pause execution
 * of the coroutine until the awaitable has resolved. If the awaitable is fulfilled, the fulfillment value is sent to
 * the generator. If the awaitable is rejected, the rejection exception is thrown into the generator.
 */
final class Coroutine extends Future
{
    /**
     * @var \Generator
     */
    private $generator;

    /**
     * @var \Closure
     */
    private $send;
    
    /**
     * @var \Closure
     */
    private $capture;

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
            if ($this->paused) { // Avoid blowing up the call stack by queuing the continuation.
                Loop\queue($this->send, $value);
                return;
            }

            try {
                // Send the new value and execute to next yield statement.
                $this->next($this->generator->send($value));
            } catch (Throwable $exception) {
                $this->reject($exception);
            }
        };

        /**
         * @param \Throwable $exception Exception to be thrown into the generator.
         */
        $this->capture = function (Throwable $exception) {
            if ($this->paused) { // Avoid blowing up the call stack by queuing the continuation.
                Loop\queue($this->capture, $exception);
                return;
            }

            try {
                // Throw exception at current execution point.
                $this->next($this->generator->throw($exception));
            } catch (Throwable $exception) {
                $this->reject($exception);
            }
        };

        try {
            $this->next($this->generator->current());
        } catch (Throwable $exception) {
            $this->reject($exception);
        }
    }

    /**
     * Examines the value yielded from the generator and prepares the next step in interation.
     *
     * @param mixed $yielded
     */
    private function next($yielded)
    {
        if (!$this->generator->valid()) {
            $this->resolve($this->generator->getReturn());
            return;
        }

        $this->paused = true;

        if ($yielded instanceof Generator) {
            $yielded = new self($yielded);
        }

        if ($yielded instanceof Awaitable) {
            $yielded->done($this->send, $this->capture);
        } else {
            Loop\queue($this->send, $yielded);
        }

        $this->paused = false;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(Throwable $reason = null)
    {
        if ($this->isPending()) {
            $current = $this->generator->current(); // Get last yielded value.
            if ($current instanceof Awaitable) {
                $current->cancel($reason); // Cancel last yielded awaitable.
            }
        }

        parent::cancel($reason);
    }
}
