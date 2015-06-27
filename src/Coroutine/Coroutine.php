<?php
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
     * @var mixed
     */
    private $current;
    
    /**
     * @var bool
     */
    private $ready = false;
    
    /**
     * @var bool
     */
    private $paused = false;

    /**
     * @var bool
     */
    private $initial = true;
    
    /**
     * @param \Generator $generator
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        
        parent::__construct(
            function ($resolve, $reject) {
                /**
                 * @param mixed $value The value to send to the generator.
                 * @param \Exception|null $exception Exception object to be thrown into the generator if not null.
                 */
                $this->worker = function ($value = null, Exception $exception = null) use ($resolve, $reject) {
                    if ($this->paused) { // If paused, mark coroutine as ready to resume.
                        $this->ready = true;
                        return;
                    }
                    
                    try {
                        if ($this->initial) { // Get result of first yield statement.
                            $this->initial = false;
                            $this->current = $this->generator->current();
                        } elseif (null !== $exception) { // Throw exception at current execution point.
                            $this->current = $this->generator->throw($exception);
                        } else { // Send the new value and execute to next yield statement.
                            $this->current = $this->generator->send($value);
                        }
                        
                        if (!$this->generator->valid()) {
                            $resolve($value);
                            $this->close();
                            return;
                        }
                        
                        if ($this->current instanceof Generator) {
                            $this->current = new self($this->current);
                        }
                        
                        if ($this->current instanceof PromiseInterface) {
                            $this->current->done($this->worker, $this->capture);
                        } else {
                            Loop\queue($this->worker, $this->current);
                        }
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
                
                Loop\queue($this->worker);
            },
            function (Exception $exception) {
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
            }
        );
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
        $this->current = null;
        
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
            
            if ($this->ready) {
                if ($this->current instanceof PromiseInterface) {
                    $this->current->done($this->worker, $this->capture);
                } else {
                    Loop\queue($this->worker, $this->current);
                }
                
                $this->ready = false;
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
