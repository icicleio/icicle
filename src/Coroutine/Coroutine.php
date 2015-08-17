<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine;

use Generator;
use Icicle\Loop;
use Icicle\Promise\{Promise, PromiseInterface};
use Throwable;

/**
 * This class implements cooperative coroutines using Generators. Coroutines should yield promises to pause execution
 * of the coroutine until the promise has resolved. If the promise is fulfilled, the fulfillment value is sent to the
 * generator. If the promise is rejected, the rejection exception is thrown into the generator.
 */
class Coroutine extends Promise implements CoroutineInterface
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
        $this->generator = $generator;

        parent::__construct(
            function (callable $resolve, callable $reject) {
                $yielded = $this->generator->current();

                if (!$this->generator->valid()) {
                    $resolve($this->generator->getReturn());
                    $this->close();
                    return;
                }

                /**
                 * @param mixed $value The value to send to the generator.
                 */
                $this->send = function ($value = null) use ($resolve, $reject) {
                    if ($this->paused) { // If paused, save callable and value for resuming.
                        $this->next = [$this->send, $value];
                        return;
                    }
                    
                    try {
                        // Send the new value and execute to next yield statement.
                        $yielded = $this->generator->send($value);

                        if (!$this->generator->valid()) {
                            $resolve($this->generator->getReturn());
                            $this->close();
                            return;
                        }

                        $this->next($yielded);
                    } catch (Throwable $exception) {
                        $reject($exception);
                        $this->close();
                    }
                };
                
                /**
                 * @param \Throwable $exception Exception to be thrown into the generator.
                 */
                $this->capture = function (Throwable $exception) use ($resolve, $reject) {
                    if ($this->paused) { // If paused, save callable and exception for resuming.
                        $this->next = [$this->capture, $exception];
                        return;
                    }

                    try {
                        // Throw exception at current execution point.
                        $yielded = $this->generator->throw($exception);

                        if (!$this->generator->valid()) {
                            $resolve($this->generator->getReturn());
                            $this->close();
                            return;
                        }

                        $this->next($yielded);
                    } catch (Throwable $exception) {
                        $reject($exception);
                        $this->close();
                    }
                };

                $this->next($yielded);

                return function (Throwable $exception)  {
                    try {
                        $current = $this->generator->current(); // Get last yielded value.
                        if ($current instanceof PromiseInterface) {
                            $current->cancel($exception);
                        }
                    } finally {
                        $this->close();
                    }
                };
            }
        );
    }

    /**
     * Examines the value yielded from the generator and prepares the next step in interation.
     *
     * @param mixed $yielded
     */
    private function next($yielded)
    {
        if ($yielded instanceof Generator) {
            $yielded = new self($yielded);
        }

        if ($yielded instanceof PromiseInterface) {
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
                Loop\queue(...$this->next);
                $this->next = null;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * {@inheritdoc}
     */
    public function cancel($reason = null)
    {
        $this->pause();
        parent::cancel($reason);
    }
}
