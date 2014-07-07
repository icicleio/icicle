<?php
namespace Icicle\Coroutine;

use Exception;
use Generator;
use Icicle\Coroutine\Exception\CancelledException;
use Icicle\Coroutine\Exception\InvalidCallableException;
use Icicle\Coroutine\Exception\UnsuccessfulCancellationException;
use Icicle\Loop\Loop;
use Icicle\Promise\Promise;
use Icicle\Promise\PromiseInterface;
use Icicle\Promise\PromiseTrait;
use Icicle\Promise\PromisorInterface;
use Icicle\Timer\Immediate;

class Coroutine implements PromiseInterface
{
    use PromiseTrait;
    
    /**
     * @var Generator
     */
    private $generator;
    
    /**
     * @var Promise
     */
    private $promise;
    
    /**
     * @var Closure
     */
    private $worker;
    
    /**
     * @var mixed
     */
    private $current;
    
    /**
     * @param   Generator $generator
     */
    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
        
        $this->promise = new Promise(
            function ($resolve, $reject) {
                /**
                 * Excutes the Generator until a yield statement is encountered. If the Generator yields another Generator object,
                 * another Coroutine is created and execution of that coroutine supersedes this Coroutine. The resolved value
                 * of the new Coroutine is used as the send value of this Coroutine. If the Generator yields a PromiseInterface
                 * or a PromisorInterface, execution of the Generator waits until the promise resolves. The resolution of the
                 * promise is either sent or thrown into the Generator.
                 *
                 * @param   mixed $value The value to send to the Generator.
                 * @param   Exception|null $exception If not null, the Exception object will be thrown into the Generator.
                 */
                $this->worker = function ($value = null, Exception $exception = null) use ($resolve, $reject) {
                    static $initial = true;
                    if ($this->promise->isPending()) { // Coroutine may have been cancelled.
                        try {
                            if (null !== $exception) { // Throw exception at current execution point.
                                $initial = false;
                                $this->current = $this->generator->throw($exception);
                            } elseif ($initial) { // Get result of first yield statement.
                                $initial = false;
                                $this->current = $this->generator->current();
                            } else { // Send the new value and execute to next yield statement.
                                $this->current = $this->generator->send($value);
                            }
                            
                            if (!$this->generator->valid()) {
                                $resolve($value);
                            } else {
                                if ($this->current instanceof Generator) {
                                    $this->current = new static($this->current);
                                } elseif ($this->current instanceof PromisorInterface) {
                                    $this->current = $this->current->getPromise();
                                }
                                
                                if ($this->current instanceof PromiseInterface) {
                                    $this->current->done(
                                        function ($value) {
                                            Immediate::enqueue($this->worker, $value);
                                        },
                                        function (Exception $exception) {
                                            Immediate::enqueue($this->worker, null, $exception);
                                        }
                                    );
                                } else {
                                    Immediate::enqueue($this->worker, $this->current);
                                }
                            }
                        } catch (Exception $exception) {
                            $reject($exception);
                        }
                    }
                };
                
                Immediate::enqueue($this->worker);
            },
            function (Exception $exception) {
                if ($this->current instanceof PromiseInterface) {
                    $this->current->cancel($exception);
                }
                
                $this->generator->throw($exception);
                
                if ($this->generator->valid()) {
                    Loop::schedule(function () {
                        throw new UnsuccessfulCancellationException($this);
                    });
                }
            }
        );
    }
    
    /**
     * @param   Exception|null $exception
     */
    public function cancel(Exception $exception = null)
    {
        if (null === $exception) {
            $exception = new CancelledException('The coroutine was terminated.');
        }
        
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
    public function fork(callable $onCancelled = null)
    {
        return $this->promise->fork($onCancelled);
    }
    
    /**
     * {@inheritdoc}
     */
    public function uncancellable()
    {
        return $this->promise->uncancellable();
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
     *
     * @return  callable
     */
    public static function lift(callable $worker)
    {
        return Promise::lift(static::async($worker));
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
    public static function create(callable $worker, array $args = [])
    {
        try {
            $generator = call_user_func_array($worker, $args);
        } catch (Exception $exception) {
            throw new InvalidCallableException('The callable threw an exception.', $worker, $exception);
        }
        
        if (!$generator instanceof Generator) {
            throw new InvalidCallableException('The callable did not produce a Generator.', $worker);
        }
        
        return new static($generator);
    }
}
