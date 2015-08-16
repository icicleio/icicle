<?php

/*
 * This file is part of Icicle, a library for writing asynchronous code in PHP using promises and coroutines.
 *
 * @copyright 2014-2015 Aaron Piotrowski. All rights reserved.
 * @license Apache-2.0 See the LICENSE file that was distributed with this source code for more information.
 */

namespace Icicle\Coroutine;

use Exception;
use Generator;
use Icicle\Loop;
use Icicle\Promise\Promise;
use Icicle\Promise\PromiseInterface;

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
    private $worker;
    
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
                    $resolve();
                    $this->close();
                    return;
                }

                /**
                 * @param mixed $value The value to send to the generator.
                 * @param \Exception|null $exception Exception object to be thrown into the generator if not null.
                 */
                $this->worker = function ($value = null, Exception $exception = null) use ($resolve, $reject) {
                    if ($this->paused) { // If paused, save parameters for use when resuming.
                        $this->next = [$value, $exception];
                        return;
                    }
                    
                    try {
                        if (null !== $exception) { // Throw exception at current execution point.
                            $yielded = $this->generator->throw($exception);
                        } else { // Send the new value and execute to next yield statement.
                            $yielded = $this->generator->send($value);
                        }
                        
                        if (!$this->generator->valid()) {
                            $resolve($value);
                            $this->close();
                            return;
                        }

                        $this->next($yielded);
                    } catch (Exception $exception) {
                        $reject($exception);
                        $this->close();
                    }
                };
                
                /**
                 * @param \Exception $exception Exception to be thrown into the generator.
                 */
                $this->capture = function (Exception $exception) {
                    if (null !== ($worker = $this->worker)) { // Coroutine may have been closed.
                        $worker(null, $exception);
                    }
                };

                $this->next($yielded);

                return function (Exception $exception)  {
                    try {
                        $current = $this->generator->current(); // Get last yielded value.
                        while ($this->generator->valid()) {
                            if ($current instanceof PromiseInterface) {
                                $current->cancel($exception);
                            }
                            $current = $this->generator->throw($exception);
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
            $yielded->done($this->worker, $this->capture);
        } else {
            Loop\queue($this->worker, $yielded);
        }
    }
    
    /**
     * The garbage collector does not automatically detect (at least not quickly) the circular references that can be
     * created, so explicitly setting these parameters to null is necessary for proper freeing of memory.
     */
    private function close()
    {
        $this->generator = null;
        $this->capture = null;
        $this->worker = null;
        $this->next = null;
        
        $this->paused = true;
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
                list($value, $exception) = $this->next;
                Loop\queue($this->worker, $value, $exception);
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
}
