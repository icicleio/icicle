<?php
namespace Icicle\Coroutine;

use Exception;
use Generator;
use Icicle\Coroutine\Exception\CancelledException;
use Icicle\Coroutine\Exception\InvalidCallableException;
use Icicle\Coroutine\Exception\InvalidGeneratorException;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Promise\PromiseInterface;
use Icicle\Promise\PromiseTrait;

class Coroutine implements CoroutineInterface
{
    use PromiseTrait;
    
    /**
     * @var Generator|null
     */
    private $generator;
    
    /**
     * @var Promise
     */
    private $promise;
    
    /**
     * @var Closure|null
     */
    private $worker;
    
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
     * @param   Generator $generator
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        
        $this->promise = new Promise(
            function ($resolve, $reject) {
                /**
                 * @param   mixed $value The value to send to the Generator.
                 * @param   Exception|null $exception If not null, the Exception object will be thrown into the Generator.
                 */
                $this->worker = function ($value = null, Exception $exception = null) use ($resolve, $reject) {
                    static $initial = true;
                    if (!$this->promise->isPending()) { // Coroutine may have been cancelled.
                        return;
                    }
                    
                    if ($this->isPaused()) { // If paused, mark coroutine as ready to resume.
                        $this->ready = true;
                        return;
                    }
                    
                    try {
                        if (null !== $exception) { // Throw exception at current execution point.
                            $initial = false;
                            $this->current = $this->generator->throw($exception);
                        } elseif ($initial) { // Get result of first yield statement.
                            $initial = false;
                            if (!$this->generator->valid()) { // Reject if initially given an invalid generator.
                                throw new InvalidGeneratorException($this->generator);
                            }
                            $this->current = $this->generator->current();
                        } else { // Send the new value and execute to next yield statement.
                            $this->current = $this->generator->send($value);
                        }
                        
                        if (!$this->generator->valid()) {
                            $resolve($value);
                            $this->close();
                            return;
                        }
                        
                        if ($this->current instanceof Generator) {
                            $this->current = new static($this->current);
                        }
                        
                        if ($this->current instanceof PromiseInterface) {
                            $this->current->done(
                                function ($value) {
                                    if ($this->promise->isPending()) {
                                        Loop::immediate($this->worker, $value);
                                    }
                                },
                                function (Exception $exception) {
                                    if ($this->promise->isPending()) {
                                        Loop::immediate($this->worker, null, $exception);
                                    }
                                }
                            );
                        } else {
                            Loop::immediate($this->worker, $this->current);
                        }
                    } catch (Exception $exception) {
                        $reject($exception);
                        $this->close();
                    }
                };
                
                Loop::immediate($this->worker);
            },
            function (Exception $exception) {
                try {
                    while ($this->generator->valid()) {
                        if ($this->current instanceof PromiseInterface) {
                            $this->current->cancel($exception);
                        }
                        
                        $this->current = $this->generator->throw($exception);
                    }
                } finally {
                    $this->close();
                }
            }
        );
    }
    
    /**
     * The garbage collector does not automatically detect the deep circular references that can be
     * created, so explicitly setting these parameters to null is necessary for proper freeing of memory.
     */
    private function close()
    {
        $this->generator = null;
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
        if ($this->promise->isPending() && $this->isPaused()) {
            $this->paused = false;
            
            if ($this->ready) {
                if ($this->current instanceof PromiseInterface) {
                    $this->current->done(
                        function ($value) {
                            if ($this->promise->isPending()) {
                                Loop::immediate($this->worker, $value);
                            }
                        },
                        function (Exception $exception) {
                            if ($this->promise->isPending()) {
                                Loop::immediate($this->worker, null, $exception);
                            }
                        }
                    );
                } else {
                    Loop::immediate($this->worker, $this->current);
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
    
    /**
     * {@inheritdoc}
     */
    public function cancel(Exception $exception = null)
    {
        $this->promise->cancel($exception);
    }
    
    /**
     * {@inheritdoc}
     */
    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        return $this->promise->then($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function done(callable $onFulfilled = null, callable $onRejected = null)
    {
        $this->promise->done($onFulfilled, $onRejected);
    }
    
    /**
     * {@inheritdoc}
     */
    public function timeout($timeout, Exception $exception = null)
    {
        return $this->promise->timeout($timeout, $exception);
    }
    
    /**
     * {@inheritdoc}
     */
    public function delay($time)
    {
        return $this->promise->delay($time);
    }
    
    /**
     * {@inheritdoc}
     */
    public function isPending()
    {
        return $this->promise->isPending();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isFulfilled()
    {
        return $this->promise->isFulfilled();
    }
    
    /**
     * {@inheritdoc}
     */
    public function isRejected()
    {
        return $this->promise->isRejected();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getResult()
    {
        return $this->promise->getResult();
    }
    
    /**
     * @param   callable $worker
     *
     * @return  callable
     */
    public static function async(callable $worker)
    {
        /**
         * @param   mixed ...$args
         *
         * @return  Coroutine
         *
         * @throws  InvalidCallableException Thrown if the callable throws an exception or does not return a Generator.
         */
        return function (/* ...$args */) use ($worker) {
            return static::create($worker, func_get_args());
        };
    }
    
    /**
     * @param   callable $worker
     * @param   mixed ...$args
     *
     * @return  Coroutine
     *
     * @throws  InvalidCallableException Thrown if the callable throws an exception or does not return a Generator.
     */
    public static function call(callable $worker /* , ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        
        return static::create($worker, $args);
    }
    
    /**
     * @param   callable $worker
     * @param   mixed[] $args
     *
     * @return  Coroutine
     *
     * @throws  InvalidCallableException Thrown if the callable throws an exception or does not return a Generator.
     */
    public static function create(callable $worker, array $args = null)
    {
        try {
            if (empty($args)) {
                $generator = $worker();
            } else {
                $generator = call_user_func_array($worker, $args);
            }
        } catch (Exception $exception) {
            throw new InvalidCallableException('The callable threw an exception.', $worker, $exception);
        }
        
        if (!$generator instanceof Generator) {
            throw new InvalidCallableException('The callable did not produce a Generator.', $worker);
        }
        
        return new static($generator);
    }
    
    /**
     * @coroutine
     *
     * @param   float $time Time to sleep in seconds.
     *
     * @return  float Actual time slept in seconds.
     */
    public static function sleep($time)
    {
        $start = (yield Promise::resolve(microtime(true))->delay($time));
        
        yield microtime(true) - $start;
    }
}
