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
     * @var mixed
     */
    private $current;

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
            if (null === $this->generator) { // Coroutine may have been cancelled.
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
            if (null === $this->generator) { // Coroutine may have been cancelled.
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

        if ($yielded instanceof Generator) {
            $yielded = new self($yielded);
        }

        $this->current = $yielded;

        if ($yielded instanceof Awaitable) {
            $yielded->done($this->send, $this->capture);
        } else {
            Loop\queue($this->send, $yielded);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function resolve($value = null)
    {
        parent::resolve($value);

        $this->generator = null;
        $this->current = null;
        $this->send = null;
        $this->capture = null;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(Throwable $reason = null)
    {
        if ($this->current instanceof Awaitable) {
            $this->current->cancel($reason);
        }

        try {
            $this->generator = null; // finally blocks may throw from force-closed Generator.
        } catch (Throwable $exception) {
            $reason = $exception;
        }

        parent::cancel($reason);
    }
}
