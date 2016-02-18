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
use Icicle\Awaitable\Awaitable;
use Icicle\Awaitable\Future;
use Icicle\Coroutine\Exception\TerminatedException;
use Icicle\Loop;

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
            try {
                // Send the new value and execute to next yield statement.
                $this->next($this->generator->send($value), $value);
            } catch (Exception $exception) {
                $this->reject($exception);
            }
        };

        /**
         * @param \Exception $exception Exception to be thrown into the generator.
         */
        $this->capture = function (Exception $exception) {
            try {
                // Throw exception at current execution point.
                $this->next($this->generator->throw($exception));
            } catch (Exception $exception) {
                $this->reject($exception);
            }
        };

        try {
            $this->next($this->generator->current());
        } catch (Exception $exception) {
            $this->reject($exception);
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
    public function cancel(Exception $reason = null)
    {
        if (null === $reason) {
            $reason = new TerminatedException();
        }

        if (!$this->isPending()) {
            return;
        }

        if ($this->current instanceof Awaitable && $this->current->isPending()) {
            $this->current->cancel($reason);

            // Resolve awaitable with cancelled awaitable. Execution will continue with thrown exception.
            $this->resolve($this->current);
            return;
        }

        try {
            // Throw exception at current yield point.
            $yielded = $this->generator->throw($reason);

            parent::cancel($reason);

            // Continue coroutine execution if a value was yielded (even though the consumer cancelled).
            $this->next($yielded);
        } catch (Exception $exception) {
            parent::cancel($exception); // Use thrown exception to cancel awaitable.
        }
    }
}
